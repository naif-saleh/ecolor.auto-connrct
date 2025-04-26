<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\Webhook\AdistProccesBatchNumbers;
use App\Models\ADistWebhookBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdistWebhookController extends Controller
{
    /**
     * Webhook endpoint to receive number data from 3CX API
     */
    public function receive(Request $request)
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

        // Create a webhook batch record to track this batch
        $webhookBatch = ADistWebhookBatch::create([
            'batch_id' => $batchId,
            'total_numbers' => count($data),
            'is_last_batch' => $isLastBatch,
            'status' => 'received',
            'user_id' => auth()->id() ?? 1,
            'received_at' => now(),
        ]);

        // Dispatch job to process the numbers
        AdistProccesBatchNumbers::dispatch($webhookBatch, $data);

        return response()->json([
            'success' => true,
            'message' => 'Webhook received successfully',
            'batch_id' => $batchId,
            'numbers_received' => count($data),
        ]);
    }

    /**
     * Check webhook batch status
     */
    public function checkStatus(Request $request, $batchId)
    {
        $batches = ADistWebhookBatch::where('batch_id', $batchId)->get();

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
