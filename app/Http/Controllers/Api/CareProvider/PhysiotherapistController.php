<?php

namespace App\Http\Controllers\Api\CareProvider;

use App\Http\Controllers\Controller;
use App\Services\PhysiotherapistService;
use App\Services\NearbyRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PhysiotherapistController extends Controller
{
    protected $physiotherapistService;
    protected $nearbyRequestService;

    public function __construct(
        PhysiotherapistService $physiotherapistService,
        NearbyRequestService $nearbyRequestService
    ) {
        $this->physiotherapistService = $physiotherapistService;
        $this->nearbyRequestService = $nearbyRequestService;
    }

    /**
     * Get nearby pending physiotherapist requests
     * Frontend must send latitude + longitude
     */
    public function nearbyRequests(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $user = Auth::user();
        $careProvider = $user->careProvider;

        if (!$careProvider || $careProvider->type !== 'physiotherapist') {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized or not a physiotherapist.'
            ], 403);
        }

        try {
            $requests = $this->nearbyRequestService->getNearbyPendingRequests(
                providerType: 'physiotherapist',
                latitude: (float) $request->latitude,
                longitude: (float) $request->longitude,
                perPage: (int) $request->get('per_page', 10)
            );

            $data = $requests->getCollection()->map(function ($visit) {
                return $this->physiotherapistService->formatNearbyRequestData($visit);
            })->values();

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'meta' => [
                    'current_page' => $requests->currentPage(),
                    'last_page' => $requests->lastPage(),
                    'per_page' => $requests->perPage(),
                    'total' => $requests->total(),
                ]
            ]);
        } catch (\Exception $e) {
            $statusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    public function schedules(Request $request)
    {
        $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'status' => 'sometimes|in:accepted,in_progress,completed,cancelled',
        ]);

        $user = Auth::user();
        $careProvider = $user->careProvider;

        if (!$careProvider || $careProvider->type !== 'physiotherapist') {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized or not a physiotherapist.'
            ], 403);
        }

        $filters = [];
        if ($request->has('status')) {
            $filters['status'] = $request->status;
        }

        $visits = $this->physiotherapistService->getSchedules($filters, $request->get('per_page', 10));

        $data = $visits->getCollection()->map(function ($visit) {
            return $this->physiotherapistService->formatScheduleData($visit);
        })->values();

        $meta = [
            'current_page' => $visits->currentPage(),
            'last_page' => $visits->lastPage(),
            'per_page' => $visits->perPage(),
            'total' => $visits->total(),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => $meta,
        ]);
    }

    /**
     * Legacy alias
     * بدل orders القديمة صار يرجع nearby requests
     */
    public function orders(Request $request)
    {
        return $this->nearbyRequests($request);
    }

    public function accept($id)
    {
        try {
            $visit = $this->physiotherapistService->acceptOrder($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Home visit accepted successfully',
                'data' => [
                    'id' => $visit->id,
                    'scheduled_at' => $visit->scheduled_at->toIso8601String(),
                    'status' => $visit->status,
                ],
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    public function startSession($id)
    {
        $user = Auth::user();
        $careProvider = $user->careProvider;

        if (!$careProvider || $careProvider->type !== 'physiotherapist') {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized or not a physiotherapist.'
            ], 403);
        }

        try {
            $visit = $this->physiotherapistService->startSession($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Session started successfully',
                'data' => [
                    'id' => $visit->id,
                    'started_at' => $visit->started_at->toIso8601String(),
                    'status' => $visit->status,
                ],
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    public function endSession($id)
    {
        $user = Auth::user();
        $careProvider = $user->careProvider;

        if (!$careProvider || $careProvider->type !== 'physiotherapist') {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized or not a physiotherapist.'
            ], 403);
        }

        try {
            $visit = $this->physiotherapistService->endSession($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Session ended successfully',
                'data' => [
                    'id' => $visit->id,
                    'ended_at' => $visit->ended_at->toIso8601String(),
                    'status' => $visit->status,
                ],
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Get physiotherapist profile
     */
    public function getProfile(Request $request)
    {
        $user = $request->user();
        $careProvider = $user->careProvider;

        if (!$careProvider || $careProvider->type !== 'physiotherapist') {
            return response()->json([
                'status' => 'error',
                'message' => 'Care provider profile not found or not a physiotherapist'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Profile retrieved successfully',
            'data' => [
                'id' => $careProvider->id,
                'type' => $careProvider->type,
                'full_name' => $user->full_name,
                'session_fee' => $careProvider->session_fee,
                'bank_account' => $careProvider->bank_account,
                'rating_avg' => $careProvider->rating_avg,
            ]
        ]);
    }

    /**
     * Update physiotherapist profile
     */
    public function updateProfile(Request $request)
    {
        $request->validate([
        'session_fee' => 'sometimes|numeric|min:0',
        'bank_account' => 'sometimes|string|max:255',
        'latitude' => 'sometimes|numeric',
        'longitude' => 'sometimes|numeric',
        ]);

        $user = $request->user();
        $careProvider = $user->careProvider;

        if (!$careProvider || $careProvider->type !== 'physiotherapist') {
            return response()->json([
                'status' => 'error',
                'message' => 'Care provider profile not found or not a physiotherapist'
            ], 404);
        }

        if ($request->has('session_fee')) {
            $careProvider->session_fee = $request->session_fee;
        }

        if ($request->has('bank_account')) {
            $careProvider->bank_account = $request->bank_account;
        }
        if ($request->has('latitude')) {
        $careProvider->latitude = $request->latitude;
        }

        if ($request->has('longitude')) {
        $careProvider->longitude = $request->longitude;
        }
        $careProvider->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Profile updated successfully',
            'data' => [
                'care_provider' => [
                    'id' => $careProvider->id,
                    'session_fee' => $careProvider->session_fee,
                    'bank_account' => $careProvider->bank_account,
                ]
            ]
        ]);
    }
}