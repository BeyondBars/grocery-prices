<?php

namespace App\Http\Controllers;

use App\Pricing\Dashboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(['data' => (new Dashboard($request->user()))->toArray()]);
    }
}
