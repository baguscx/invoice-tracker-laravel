<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\InvoiceTrackerService;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicTrackingController extends Controller
{
    public function __construct(private InvoiceTrackerService $tracker) {}

    public function index(Request $request): View
    {
        $query = trim((string) $request->query('code', ''));
        $results = [];
        $error = null;

        if (preg_match('/\A[A-Za-z0-9]{64}\z/', $query) === 1) {
            $invoice = Invoice::where('public_tracking_token', $query)->first();
            $results = $invoice ? [$this->tracker->publicTrackingData($invoice)] : [];
        }

        return view('tracking.index', compact('query', 'results', 'error'));
    }

    public function show(string $token): View
    {
        $invoice = Invoice::where('public_tracking_token', $token)->firstOrFail();

        return view('tracking.index', [
            'query' => '',
            'results' => [$this->tracker->publicTrackingData($invoice)],
            'error' => null,
        ]);
    }

    public function qr(string $token): Response
    {
        abort_unless(Invoice::where('public_tracking_token', $token)->exists(), 404);

        $result = (new SvgWriter())->write(new QrCode(
            data: route('tracking.show', $token),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 240,
            margin: 12,
        ));

        return response($result->getString(), 200, [
            'Content-Type' => $result->getMimeType(),
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
