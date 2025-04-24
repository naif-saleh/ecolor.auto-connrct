<?php

namespace App\Jobs\Webhook;

use App\Models\ADialData;
use App\Models\ADialFeed;
use App\Models\ADialProvider;
use App\Models\ADialWebhookBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Carbon\Carbon;
use Illuminate\Foundation\Bus\Dispatchable;
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

    // For large batches, you might need to increase timeout
    public $timeout = 600; // 10 minutes

    // For very large batches, you might want to make this job unique
    public $uniqueFor = 3600; // 1 hour

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(ADialWebhookBatch $webhookBatch, array $data)
    {
        $this->webhookBatch = $webhookBatch;
        $this->data = $data;
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

        $this->webhookBatch->update(['status' => 'processing', 'processing_started_at' => now()]);

        DB::disableQueryLog();
        Log::info('Webhook Batch Processing Started', ['batch_id' => $this->webhookBatch->batch_id]);

        try {
            foreach ($this->data as $record) {
                // Extract data from webhook record
                $mobile = $record['mobile'] ?? null;
                $name = $record['name'] ?? null;
                $extension = $record['extension'] ?? null;
                $from = $record['from'] ?? null;
                $to = $record['to'] ?? null;
                $date = $record['date'] ?? null;

                // Skip records with missing required fields
                if (!$mobile || !$name || !$extension || !$from || !$to || !$date) {
                    $errors[] = "Incomplete record data: " . json_encode($record);
                    continue;
                }

                // Cache provider lookup (avoid duplicate queries)
                $providerKey = $name.'-'.$extension;
                if (!isset($providerCache[$providerKey])) {
                    $providerCache[$providerKey] = ADialProvider::firstOrCreate(
                        ['name' => $name, 'extension' => $extension],
                        ['user_id' => $this->webhookBatch->user_id]
                    );
                }
                $provider = $providerCache[$providerKey];

                if (!$provider) {
                    $errors[] = "Failed to find or create provider for extension: $extension";
                    continue;
                }

                // Cache feed lookup (avoid duplicate queries)
                $feedKey = $provider->id.'-'.$from.'-'.$to.'-'.$date;
                if (!isset($feedCache[$feedKey])) {
                    try {
                        $fromFormatted = Carbon::createFromFormat('h:i A', $from)->format('H:i:s');
                        $toFormatted = Carbon::createFromFormat('h:i A', $to)->format('H:i:s');
                        $dateFormatted = Carbon::parse($date)->format('Y-m-d');

                        $feedCache[$feedKey] = ADialFeed::firstOrCreate(
                            [
                                'provider_id' => $provider->id,
                                'from' => $fromFormatted,
                                'to' => $toFormatted,
                                'date' => $dateFormatted,
                            ],
                            [
                                'file_name' => $name,
                                'slug' => Str::uuid(),
                                'uploaded_by' => $this->webhookBatch->user_id,
                            ]
                        );
                    } catch (\Exception $e) {
                        $errors[] = "Date/time format error: {$e->getMessage()} for record: " . json_encode($record);
                        continue;
                    }
                }
                $feed = $feedCache[$feedKey];

                // Add to batch for insertion
                $dataBatch[] = [
                    'feed_id' => $feed->id,
                    'mobile' => $mobile,
                    'state' => 'new',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $processedRecords[] = [
                    'mobile' => $mobile,
                    'status' => 'imported'
                ];

                $successCount++;

                // Batch insert every $batchSize records
                if (count($dataBatch) >= $batchSize) {
                    $this->insertBatch($dataBatch);
                    $dataBatch = []; // Clear batch
                }
            }

            // Insert any remaining records
            if (!empty($dataBatch)) {
                $this->insertBatch($dataBatch);
            }

            // Update batch with results
            $this->webhookBatch->update([
                'status' => 'completed',
                'processing_completed_at' => now(),
                'records_processed' => $successCount,
                'records_failed' => count($errors),
                'results' => json_encode([
                    'success' => $successCount,
                    'errors' => $errors,
                    'processed_records' => $processedRecords
                ])
            ]);

            Log::info('Webhook Batch Processing Completed', [
                'batch_id' => $this->webhookBatch->batch_id,
                'success' => $successCount,
                'errors' => count($errors)
            ]);

        } catch (\Exception $e) {
            $this->webhookBatch->update([
                'status' => 'failed',
                'processing_completed_at' => now(),
                'results' => json_encode([
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ])
            ]);

            Log::error('Webhook Batch Processing Failed', [
                'batch_id' => $this->webhookBatch->batch_id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Insert a batch of records
     *
     * @param array $batch
     * @return void
     */
    protected function insertBatch(array $batch)
    {
        DB::transaction(function () use ($batch) {
            ADialData::insert($batch);
        });
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
