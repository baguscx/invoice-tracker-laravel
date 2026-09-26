<?php

namespace App\Http\Controllers;

use App\Services\InvoiceTrackerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceTransitionController extends Controller
{
    public function __construct(private InvoiceTrackerService $tracker) {}

    public function update(Request $request, string $invoice): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'string'],
            'note' => ['nullable', 'string'],
            'targetUsername' => ['nullable', 'string'],
        ]);

        return response()->json($this->tracker->transition(
            $request->user(),
            $invoice,
            $data['action'],
            $data['note'] ?? '',
            $data['targetUsername'] ?? ''
        ));
    }
}
