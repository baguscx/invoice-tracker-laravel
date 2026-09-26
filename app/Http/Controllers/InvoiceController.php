<?php

namespace App\Http\Controllers;

use App\Services\InvoiceTrackerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceTrackerService $tracker) {}

    public function store(Request $request): JsonResponse
    {
        return response()->json($this->tracker->create($request->user(), $request->all()));
    }

    public function update(Request $request, string $invoice): JsonResponse
    {
        return response()->json($this->tracker->update($request->user(), $invoice, $request->all()));
    }

    public function updateWork(Request $request, string $invoice): JsonResponse
    {
        return response()->json($this->tracker->updateWorkInfo($request->user(), $invoice, $request->all()));
    }

    public function history(Request $request, string $invoice): JsonResponse
    {
        return response()->json($this->tracker->history($request->user(), $invoice));
    }

    public function destroy(Request $request, string $invoice): JsonResponse
    {
        return response()->json($this->tracker->delete($request->user(), $invoice));
    }
}
