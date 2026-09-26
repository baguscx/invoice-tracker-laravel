<?php

namespace App\Http\Controllers;

use App\Services\InvoiceTrackerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function __construct(private InvoiceTrackerService $tracker) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->tracker->adminUsers($request->user()));
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json($this->tracker->saveAdminUser($request->user(), $request->all()));
    }
}
