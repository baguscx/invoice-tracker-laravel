<?php

namespace App\Http\Controllers;

use App\Services\InvoiceTrackerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminActivityController extends Controller
{
    public function __construct(private InvoiceTrackerService $tracker) {}

    public function recent(Request $request): JsonResponse
    {
        $limit = max(1, min(100, (int) $request->query('limit', 30)));
        return response()->json($this->tracker->recentActivity($request->user(), $limit));
    }

    public function cancelled(Request $request): JsonResponse
    {
        return response()->json($this->tracker->cancelled($request->user()));
    }
}
