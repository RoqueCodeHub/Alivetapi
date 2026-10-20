<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $database = 'ok';

        try {
            DB::connection()->getPdo();
        } catch (\Throwable) {
            $database = 'error';
        }

        return ApiResponse::success([
            'status' => 'ok',
            'app' => config('app.name'),
            'env' => config('app.env'),
            'database' => $database,
            'time' => now()->toIso8601String(),
        ], 'API v1 operativa');
    }
}
