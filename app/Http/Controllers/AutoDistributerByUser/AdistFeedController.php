<?php

namespace App\Http\Controllers\AutoDistributerByUser;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ADistFeed;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AdistFeedController extends Controller
{
    public function getTodayFeeds()
    {
        $todayFeeds = ADistFeed::whereDate('created_at', Carbon::today())->get();
        return response()->json($todayFeeds);
    }


    public function updateFeedStatus(Request $request)
    {
        try {
            // Validate the incoming request
            $request->validate([
                'feed_ids' => 'required|array',
                'feed_ids.*' => 'exists:a_dist_feeds,id',
                'status' => 'required|in:0,1'
            ]);
            Log::info('Request Data', ['feed_ids' => $request->feed_ids, 'allow' => $request->status]);

            // Update the feed allow
            $updated = ADistFeed::whereIn('id', $request->feed_ids)
                ->update(['allow' => $request->status]);

            // If no rows are updated, return an error response
            if ($updated === 0) {
                return response()->json(['error' => 'No records updated'], 400);
            }

            return response()->json(['message' => 'allow updated successfully!']);
        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('Error updating feed allow', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'An error occurred while updating the feed stallowatus.'], 500);
        }
    }


    public function deleteFeeds(Request $request)
    {
        try {
            // Validate the incoming request
            $request->validate([
                'feed_ids' => 'required|array',
                'feed_ids.*' => 'exists:a_dist_feeds,id',
            ]);

            // Delete the selected feeds
            $deleted = ADistFeed::whereIn('id', $request->feed_ids)->delete();

            // If no rows are deleted, return an error response
            if ($deleted === 0) {
                return response()->json(['error' => 'No records deleted'], 400);
            }

            return response()->json(['message' => 'Feeds deleted successfully!']);
        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('Error deleting feeds', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'An error occurred while deleting the feeds.'], 500);
        }
    }
}
