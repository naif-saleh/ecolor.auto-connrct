<?php

namespace App\Jobs\Webhook;

use App\Models\ADistAgent;
use App\Models\ADistData;
use App\Models\ADistFeed;
use App\Models\ADistSkippedNumbers;
use App\Models\ADistWebhookBatch;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AdistProccesBatchNumbers implements ShouldQueue
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
    public function __construct(ADistWebhookBatch $webhookBatch, array $data)
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
        $skippedNumbers = [];
        $successCount = 0;
        $validRows = [];
        $agentCache = [];
        $feedCache = [];
        $batchSize = 1000;
        $seenNumbers = [];

        try {
            $this->webhookBatch->update(['status' => 'processing', 'processing_started_at' => now()]);

            DB::disableQueryLog();
            Log::info('Webhook Batch Processing Started', ['batch_id' => $this->webhookBatch->batch_id]);

            // Preload ONLY active agents
            $activeAgents = ADistAgent::where('is_active', true)->pluck('extension', 'id')->toArray();
            $activeExtensions = array_flip($activeAgents);

            foreach ($this->data as $item) {
                $mobile = $item['mobile'];
                $name = $item['name'] ?? '';
                $extension = $item['extension'];
                $from = $item['from'];
                $to = $item['to'];
                $date = $item['date'];

                // Check if extension is valid AND agent is active
                if (! isset($activeExtensions[$extension])) {
                    $skippedNumbers[] = "$mobile - ⚠️ Agent Not Found or Inactive (Ext: $extension)";

                    continue;
                }

                // Validate mobile number (only numeric)
                if (! preg_match('/^\d+$/', $mobile)) {
                    $skippedNumbers[] = "$mobile - ❌ Contains non-numeric characters";

                    continue;
                }

                // Check for duplicate mobile numbers
                if (isset($seenNumbers[$mobile])) {
                    $skippedNumbers[] = "$mobile - 🔁 Duplicate Entry in Batch";

                    continue;
                }
                $seenNumbers[$mobile] = true;

                // Get agent ID from our preloaded active agents
                $agentId = array_search($extension, $activeAgents);

                // Cache agent lookup (avoid repeated queries)
                if (! isset($agentCache[$extension])) {
                    $agentCache[$extension] = ADistAgent::find($agentId);
                }
                $agent = $agentCache[$extension];

                // Format date and time
                $fromFormatted = Carbon::createFromFormat('h:i A', $from)->format('H:i:s');
                $toFormatted = Carbon::createFromFormat('h:i A', $to)->format('H:i:s');
                $dateFormatted = Carbon::parse($date)->format('Y-m-d');

                // Cache feed lookup (avoid repeated queries)
                $feedKey = "{$agent->id}-{$fromFormatted}-{$toFormatted}-{$dateFormatted}";
                if (! isset($feedCache[$feedKey])) {
                    $feedCache[$feedKey] = ADistFeed::create([
                        'agent_id' => $agent->id,
                        'from' => $fromFormatted,
                        'to' => $toFormatted,
                        'date' => $dateFormatted,
                        'file_name' => $name,
                        'slug' => Str::uuid(),
                        'uploaded_by' => 1,
                        'webhook_batch_id' => $this->webhookBatch->id,
                    ]);
                }
                $feed = $feedCache[$feedKey];

                // Prepare data for batch insert
                $validRows[] = [
                    'feed_id' => $feed->id,
                    'mobile' => $mobile,
                    'state' => 'new',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $successCount++;

                // Batch insert every 1000 records
                if (count($validRows) >= $batchSize) {
                    DB::transaction(function () use (&$validRows) {
                        ADistData::insert($validRows);
                    });
                    $validRows = []; // Clear batch
                }
            }

            // Insert remaining records
            if (! empty($validRows)) {
                DB::transaction(function () use (&$validRows) {
                    ADistData::insert($validRows);
                });
            }

            // Bulk insert skipped numbers instead of looping
            if (! empty($skippedNumbers)) {
                $skippedInsertData = [];
                foreach ($skippedNumbers as $skipped) {
                    [$mobile, $message] = explode(' - ', $skipped);
                    preg_match('/(Ext: \d+)/', $message, $matches);
                    $extension = $matches[0] ?? null;

                    $skippedInsertData[] = [
                        'mobile' => $mobile,
                        'message' => $message,
                        'uploaded_by' => $this->webhookBatch->user_id ?? null,
                        'agent_id' => $feed->agent_id ?? null,
                        'feed_id' => $feed->id ?? null,
                        'webhook_batch_id' => $this->webhookBatch->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if (! empty($skippedInsertData)) {
                    DB::transaction(function () use ($skippedInsertData) {
                        ADistSkippedNumbers::insert($skippedInsertData);
                    });
                }
            }

            // Update batch completion status
            $this->webhookBatch->update([
                'status' => 'completed',
                'completed_at' => now(),
                'processed_numbers' => $successCount,
                'skipped_numbers' => count($skippedNumbers),
                'errors' => count($skippedNumbers) > 0 ? json_encode($skippedNumbers) : null,
            ]);

            Log::info('Webhook Batch Completed Successfully', [
                'batch_id' => $this->webhookBatch->batch_id,
                'processed' => $successCount,
                'skipped' => count($skippedNumbers),
            ]);

        } catch (\Exception $e) {
            $this->webhookBatch->update([
                'status' => 'failed',
                'completed_at' => now(),
                'errors' => json_encode(['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]),
            ]);

            Log::error('Webhook Batch Processing Failed', [
                'batch_id' => $this->webhookBatch->batch_id,
                'exception' => $e->getMessage(),
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
