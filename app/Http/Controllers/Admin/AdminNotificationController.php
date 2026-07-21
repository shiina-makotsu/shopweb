<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminNotificationSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    public function __invoke(Request $request, AdminNotificationSummary $summary): JsonResponse
    {
        return response()->json($summary->data($request->boolean('fresh')));
    }
}
