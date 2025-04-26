<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\Webhook\AdialProccesBatchNumbers;
use App\Models\ADialProvider;
use App\Models\ADialWebhookBatch;
use App\Services\LicenseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdialWebhookController extends Controller
{
    protected $licenseService;

    public function __construct(LicenseService $licenseService)
    {
        $this->licenseService = $licenseService;
    }

    /**
     * Webhook endpoint to receive number data from 3CX API
     */
    public function receive(Request $request, LicenseService $licenseService)
    {
        // Validate the request payload
        $validator = Validator::make($request->all(), [
            'data' => 'required|array',
            'data.*.mobile' => 'required|string',
            'data.*.name' => 'nullable|string',
            'data.*.extension' => 'required|string',
            'data.*.from' => 'required|string',
            'data.*.to' => 'required|string',
            'data.*.date' => 'required|string',
            'batch_id' => 'nullable|string',
            'is_last_batch' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            Log::error('Webhook validation failed', ['errors' => $validator->errors()->toArray()]);

            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->input('data');
        $batchId = $request->input('batch_id') ?? Str::uuid()->toString();
        $isLastBatch = $request->input('is_last_batch') ?? false;

        // Check license BEFORE creating batch record
        $max_providers = $licenseService->getMaxAutoDialerProvider();

        if ($max_providers <= 0) {
            Log::warning('License check failed: Maximum providers limit reached', [
                'user_id' => auth()->id() ?? 1,
                'batch_id' => $batchId,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Maximum Providers Limit Reached. Please upgrade your license.',
                'code' => 'LICENSE_LIMIT_REACHED',
            ], 403);
        }

        // Perform provider existence check before creating batch
        // This prevents creating batches for data that can't be processed
        $uniqueProviders = [];
        $validRecords = [];
        $invalidRecords = [];

        foreach ($data as $record) {
            $name = $record['name'] ?? null;
            $extension = $record['extension'] ?? null;

            if (! $name || ! $extension) {
                $invalidRecords[] = $record;

                continue;
            }

            $providerKey = $name.'-'.$extension;

            // Only check once per unique provider in this batch
            if (! isset($uniqueProviders[$providerKey])) {
                $provider = ADialProvider::where('name', htmlspecialchars($name))
                    ->where('extension', htmlspecialchars($extension))
                    ->first();

                $uniqueProviders[$providerKey] = $provider ? true : false;
            }

            if ($uniqueProviders[$providerKey]) {
                $validRecords[] = $record;
            } else {
                $invalidRecords[] = $record;
            }
        }

        // If no valid records with existing providers, return error
        if (empty($validRecords)) {
            Log::warning('No valid providers found in batch', [
                'batch_id' => $batchId,
                'invalid_count' => count($invalidRecords),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'No valid providers found in submitted data. Please ensure providers exist before submitting.',
                'code' => 'NO_VALID_PROVIDERS',
            ], 422);
        }

        // Create a webhook batch record to track this batch (only with valid records)
        $webhookBatch = ADialWebhookBatch::create([
            'batch_id' => $batchId,
            'total_numbers' => count($validRecords),
            'is_last_batch' => $isLastBatch,
            'status' => 'received',
            'user_id' => auth()->id() ?? 1,
            'received_at' => now(),
        ]);

        // Dispatch job to process the numbers (only valid records)
        AdialProccesBatchNumbers::dispatch($webhookBatch, $licenseService, $validRecords);

        // Inform user about valid and invalid records
        return response()->json([
            'success' => true,
            'message' => 'Webhook received successfully',
            'batch_id' => $batchId,
            'numbers_received' => count($validRecords),
            'numbers_rejected' => count($invalidRecords),
            'status' => count($invalidRecords) > 0 ? 'partial' : 'complete',
        ]);
    }

    /**
     * Check webhook batch status
     */
    public function checkStatus(Request $request, $batchId)
    {
        $batches = ADialWebhookBatch::where('batch_id', $batchId)->get();

        if ($batches->isEmpty()) {
            return response()->json(['error' => 'Batch not found'], 404);
        }

        $totalReceived = $batches->sum('total_numbers');
        $totalProcessed = $batches->sum('processed_numbers');
        $totalSkipped = $batches->sum('skipped_numbers');
        $isComplete = $batches->where('is_last_batch', true)->where('status', 'completed')->count() > 0;

        return response()->json([
            'batch_id' => $batchId,
            'status' => $isComplete ? 'completed' : 'processing',
            'total_received' => $totalReceived,
            'total_processed' => $totalProcessed,
            'total_skipped' => $totalSkipped,
            'batches' => $batches->map(function ($batch) {
                return [
                    'id' => $batch->id,
                    'status' => $batch->status,
                    'received_at' => $batch->received_at,
                    'completed_at' => $batch->completed_at,
                ];
            }),
        ]);
    }
}
