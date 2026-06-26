<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OSRMService
{
    public function getRoute(float $startLat, float $startLng, float $endLat, float $endLng): ?array
    {
        $baseUrl = config('services.osrm.url');

        $url = rtrim($baseUrl, '/') . '/route/v1/driving/' . $startLng . ',' . $startLat . ';' . $endLng . ',' . $endLat;

        $response = Http::timeout(10)->get($url, [
            'overview' => 'false',
            'alternatives' => 'false',
            'steps' => 'false',
        ]);

        if (!$response->successful()) {
            return null;
        }

        $payload = $response->json();

        if (empty($payload['routes'][0])) {
            return null;
        }

        $route = $payload['routes'][0];

        return [
            'distance_meters' => $route['distance'] ?? null,
            'duration_seconds' => $route['duration'] ?? null,
            'distance_km' => isset($route['distance']) ? round($route['distance'] / 1000, 2) : null,
            'duration_minutes' => isset($route['duration']) ? round($route['duration'] / 60, 1) : null,
        ];
    }
}
