<?php

namespace App\Http\Controllers;

use App\Services\InvoiceTrackerService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PublicTrackingController extends Controller
{
    public function __construct(private InvoiceTrackerService $tracker) {}

    public function index(Request $request): View
    {
        $query = trim((string) $request->query('q', ''));
        $results = [];
        $error = null;

        if ($query !== '') {
            try {
                $results = $this->tracker->publicSearch($query)['results'];
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
        }

        return view('tracking.index', compact('query', 'results', 'error'));
    }
}
