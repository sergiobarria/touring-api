<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Spatie\Health\ResultStores\ResultStore;
use Throwable;

class HealthCheckController extends Controller
{
    public function __invoke(ResultStore $resultStore): JsonResponse
    {
        try {
            $results = $resultStore->latestResults();
            $healthy = $results?->allChecksOk() === true
                && $results->finishedAt->getTimestamp() >= now()->subMinutes(2)->getTimestamp();
        } catch (Throwable $exception) {
            report($exception);

            return $this->response(healthy: false);
        }

        return $this->response($healthy);
    }

    private function response(bool $healthy): JsonResponse
    {
        return response()
            ->json(['healthy' => $healthy], $healthy ? 200 : 503)
            ->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0',
            ]);
    }
}
