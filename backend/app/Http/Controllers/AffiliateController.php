<?php

namespace App\Http\Controllers;

use App\Services\OrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AffiliateController extends Controller
{
    public function __construct(
        private readonly OrderService $service,
    ) {}

    // GET /api/affiliates/{id}/summary
    public function summary(int $id): JsonResponse
    {
        return ApiResponse::success($this->service->affiliateSummary($id));
    }
}