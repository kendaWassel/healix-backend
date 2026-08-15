<?php

namespace App\Http\Controllers\Api\CareProvider;

use App\Http\Controllers\Controller;
use App\Http\Requests\CareProviderLocationUpdateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CareProviderLocationController extends Controller
{
    /**
     * POST /api/provider/location/update
     *
     * Lightweight endpoint for the nurse/physiotherapist app to keep the
     * provider's current position fresh — unlike the full profile update,
     * this doesn't touch name/phone/files, so it's cheap to call often
     * (e.g. whenever the "available for requests" screen is open).
     * Shared across both provider types since the underlying record and
     * update are identical.
     */
    public function updateLocation(CareProviderLocationUpdateRequest $request): JsonResponse
    {
        $careProvider = Auth::user()->careProvider;

        if (!$careProvider) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.care_provider_not_found'),
            ], 404);
        }

        $careProvider->update([
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'last_location_updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('messages.location_updated'),
            'data' => [
                'latitude' => $careProvider->latitude,
                'longitude' => $careProvider->longitude,
                'updated_at' => $careProvider->last_location_updated_at->toIso8601String(),
                'is_online' => $careProvider->isOnline(),
            ],
        ]);
    }
}
