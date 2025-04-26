<?php

namespace App\Jobs\Webhook;

use App\Models\ADialData;
use App\Models\ADialFeed;
use App\Models\ADialProvider;
use App\Models\ADialWebhookBatch;
use App\Services\LicenseService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AdialProccesBatchNumbers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $webhookBatch;

    protected $data;

    protected $licenseService;

    // For large batches, you might need to increase timeout
    public $timeout = 600; // 10 minutes

    // For very large batches, you might want to make this job unique
    public $uniqueFor = 3600; // 1 hour

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(ADialWebhookBatch $webhookBatch, LicenseService $licenseService, array $data)
    {
        $this->webhookBatch = $webhookBatch;
        $this->data = $data;
        $this->licenseService = $licenseService;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $errors = [];
        $successCount = 0;
        $batchSize = 1000;
        $dataBatch = [];
        $providerCache = [];
        $feedCache = [];
        $processedRecords = [];
        $providerFoundCount = 0;
        $providerNotFoundCount = 0;

        $this->webhookBatch->update(['status' => 'processing', 'processing_started_at' => now()]);

        DB::disableQueryLog();
        Log::info('Webhook Batch Processing Started', [
            'batch_id' => $this->webhookBatch->batch_id,
            'record_count' => count($this->data),
        ]);

        try {
            foreach ($this->data as $index => $record) {
                // Extract data from webhook record
                $mobile = $record['mobile'] ?? null;
                $name = $record['name'] ?? null;
                $extension = $record['extension'] ?? null;
                $from = $record['from'] ?? null;
                $to = $record['to'] ?? null;
                $date = $record['date'] ?? null;

                // Skip records with missing required fields
                if (! $mobile || ! $name || ! $extension || ! $from || ! $to || ! $date) {
                    $errors[] = 'Incomplete record data: '.json_encode($record);
                    continue;
                }

                try {
                    // STRICT PROVIDER CHECK with detailed logging
                    $providerKey = $name.'-'.$extension;

                    // Log the lookup attempt
                    Log::info('Looking up provider', [
                        'name' => $name,
                        'extension' => $extension,
                        'record_index' => $index
                    ]);

                    if (! isset($providerCache[$providerKey])) {
                        // First check for exact match on both name and extension
                        $sanitizedName = htmlspecialchars($name);
                        $sanitizedExtension = htmlspecialchars($extension);

                        $provider = ADialProvider::where('name', $sanitizedName)
                            ->where('extension', $sanitizedExtension)
                            ->first();

                        // Log the query result
                        if ($provider) {
                            Log::info('Provider found in database', [
                                'provider_id' => $provider->id,
                                'name' => $provider->name,
                                'extension' => $provider->extension
                            ]);
                        } else {
                            // For debugging, check if there's a provider with either name or extension
                            $nameMatch = ADialProvider::where('name', $sanitizedName)->first();
                            $extMatch = ADialProvider::where('extension', $sanitizedExtension)->first();

                            Log::warning('Provider not found in database', [
                                'name' => $sanitizedName,
                                'extension' => $sanitizedExtension,
                                'name_match_exists' => (bool)$nameMatch,
                                'extension_match_exists' => (bool)$extMatch
                            ]);
                        }

                        $providerCache[$providerKey] = $provider;
                    }

                    $provider = $providerCache[$providerKey];

                    // If provider doesn't exist, log error and skip this record
                    if (! $provider || ! $provider->id) {
                        $providerNotFoundCount++;
                        $errors[] = "Provider not found for: name={$name}, extension={$extension}";

                        // Add to processed records with failed status
                        $processedRecords[] = [
                            'mobile' => $mobile,
                            'status' => 'failed',
                            'reason' => 'provider_not_found',
                        ];

                        continue;
                    }

                    $providerFoundCount++;

                    // Rest of your existing code...
                    $fromFormatted = $this->parseTime($from);
                    $toFormatted = $this->parseTime($to);
                    $dateFormatted = $this->parseDate($date);

                    if (! $fromFormatted || ! $toFormatted || ! $dateFormatted) {
                        $errors[] = "Invalid date/time format: from={$from}, to={$to}, date={$date}";
                        continue;
                    }

                    if (! $this->webhookBatch->user_id) {
                        Log::warning('WebhookBatch has no user_id, using default', ['batch_id' => $this->webhookBatch->batch_id]);
                        $this->webhookBatch->update(['user_id' => 1]);
                    }

                    // Only create feed if provider exists
                    $feedKey = "{$provider->id}-{$fromFormatted}-{$toFormatted}-{$dateFormatted}";

                    $feed = $feedCache[$feedKey] ??= ADialFeed::create([
                        'provider_id' => $provider->id,
                        'from' => $fromFormatted,
                        'to' => $toFormatted,
                        'date' => $dateFormatted,
                        'file_name' => htmlspecialchars($name),
                        'slug' => Str::uuid(),
                        'uploaded_by' => $this->webhookBatch->user_id,
                        'webhook_batch_id' => $this->webhookBatch->id,
                    ]);

                    // Add to batch for insertion
                    $dataBatch[] = [
                        'feed_id' => $feed->id,
                        'mobile' => htmlspecialchars($mobile),
                        'state' => 'new',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $processedRecords[] = [
                        'mobile' => $mobile,
                        'status' => 'imported',
                    ];

                    $successCount++;

                    // Batch insert every $batchSize records
                    if (count($dataBatch) >= $batchSize) {
                        $this->insertBatch($dataBatch);
                        Log::info('Inserted batch', ['size' => count($dataBatch)]);
                        $dataBatch = []; // Clear batch
                    }
                } catch (\Exception $e) {
                    $errors[] = "Error processing record {$index}: ".$e->getMessage();
                    Log::error('Error processing record', [
                        'index' => $index,
                        'error' => $e->getMessage(),
                        'record' => $record,
                    ]);
                }
            }

            // Insert any remaining records
            if (! empty($dataBatch)) {
                $this->insertBatch($dataBatch);
                Log::info('Inserted final batch', ['size' => count($dataBatch)]);
            }

            // Log provider statistics
            Log::info('Provider lookup statistics', [
                'providers_found' => $providerFoundCount,
                'providers_not_found' => $providerNotFoundCount,
                'total_lookups' => $providerFoundCount + $providerNotFoundCount
            ]);

            // Update batch with results
            $this->webhookBatch->update([
                'status' => 'completed',
                'processing_completed_at' => now(),
                'records_processed' => $successCount,
                'records_failed' => count($errors),
                'results' => json_encode([
                    'success' => $successCount,
                    'errors' => array_slice($errors, 0, 1000), // Limit error list size
                    'processed_records' => array_slice($processedRecords, 0, 1000), // Limit for large batches
                    'providers_found' => $providerFoundCount,
                    'providers_not_found' => $providerNotFoundCount
                ]),
            ]);

            Log::info('Webhook Batch Processing Completed', [
                'status' => $this->webhookBatch->status,
                'batch_id' => $this->webhookBatch->id,
                'processing_completed_at' => $this->webhookBatch->processing_completed_at,
                'records_failed' => count($errors),
                'success' => $successCount,
                'errors' => count($errors),
                'providers_found' => $providerFoundCount,
                'providers_not_found' => $providerNotFoundCount
            ]);

        } catch (\Exception $e) {
            $this->webhookBatch->update([
                'status' => 'failed',
                'processing_completed_at' => now(),
                'results' => json_encode([
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]),
            ]);

            Log::error('Webhook Batch Processing Failed', [
                'batch_id' => $this->webhookBatch->batch_id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Parse time string with multiple format support
     *
     * @param  string  $timeStr
     * @return string|null
     */
    protected function parseTime($timeStr)
    {
        $formats = ['h:i A', 'H:i:s', 'H:i'];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $timeStr)->format('H:i:s');
            } catch (\Exception $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * Parse date string with multiple format support
     *
     * @param  string  $dateStr
     * @return string|null
     */
    protected function parseDate($dateStr)
    {
        try {
            return Carbon::parse($dateStr)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Insert a batch of records
     *
     * @return void
     */
    protected function insertBatch(array $batch)
    {
        try {
            DB::beginTransaction();
            ADialData::insert($batch);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Batch insertion failed', [
                'error' => $e->getMessage(),
                'batch_size' => count($batch),
            ]);
            throw $e;
        }
    }

    /**
     * The unique ID of the job.
     *
     * @return string
     */
    public function uniqueId()
    {
        return 'process_numbers_'.$this->webhookBatch->id;
    }
}
