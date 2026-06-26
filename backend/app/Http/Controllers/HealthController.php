<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function index(): JsonResponse
    {
        $services = [
            'mysql'  => $this->checkMysql(),
            'redis'  => $this->checkRedis(),
            'worker' => $this->checkWorker(),
        ];

        $allUp = collect($services)->every(fn ($s) => $s['status'] === 'up');

        return response()->json([
            'data' => [
                'status'   => $allUp ? 'healthy' : 'degraded',
                'services' => $services,
            ],
            'meta'   => ['checked_at' => now()->toIso8601String()],
            'errors' => null,
        ], $allUp ? 200 : 503);
    }

    private function checkMysql(): array
    {
        try {
            DB::select('SELECT 1');
            return ['status' => 'up'];
        } catch (\Throwable $e) {
            return ['status' => 'down', 'error' => $e->getMessage()];
        }
    }

    private function checkRedis(): array
    {
        try {
            Cache::store('redis')->put('health:ping', '1', 10);
            $ok = Cache::store('redis')->get('health:ping') === '1';
            return $ok ? ['status' => 'up'] : ['status' => 'down', 'error' => 'leitura falhou'];
        } catch (\Throwable $e) {
            return ['status' => 'down', 'error' => $e->getMessage()];
        }
    }

    private function checkWorker(): array
    {
        try {
            $beat = Cache::store('redis')->get('worker:heartbeat');
            if ($beat && (time() - (int) $beat) < 60) {
                return ['status' => 'up'];
            }
            return ['status' => 'unknown', 'note' => 'sem heartbeat recente'];
        } catch (\Throwable $e) {
            return ['status' => 'down', 'error' => $e->getMessage()];
        }
    }
}