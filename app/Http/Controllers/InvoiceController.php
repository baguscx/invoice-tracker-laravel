<?php

namespace App\Http\Controllers;

use App\Services\InvoiceTrackerService;
use App\Services\InvoiceSpreadsheetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceTrackerService $tracker,
        private InvoiceSpreadsheetService $spreadsheets,
    ) {}

    public function store(Request $request): JsonResponse
    {
        return response()->json($this->tracker->create($request->user(), $request->all()));
    }

    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ]);

        return response()->json($this->spreadsheets->import(
            $request->user(),
            $validated['file'],
            $this->tracker,
        ));
    }

    public function importTemplate(Request $request): BinaryFileResponse
    {
        return response()->download(
            $this->spreadsheets->createTemplate($request->user()),
            'template-import-invoice.xlsx',
        )->deleteFileAfterSend(true);
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
