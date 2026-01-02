<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PhysiotherapistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PhysiotherapistController extends Controller
{
    protected $physiotherapistService;

    public function __construct(PhysiotherapistService $physiotherapistService)
    {
        $this->physiotherapistService = $physiotherapistService;
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

    public function orders(Request $request)
    {
        $request->validate([
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

        $perPage = $request->get('per_page', 10);
        $orders = $this->physiotherapistService->getOrders($perPage);

        $data = $orders->getCollection()->map(function ($visit) {
            return $this->physiotherapistService->formatOrderData($visit);
        })->values();

        $meta = [
            'current_page' => $orders->currentPage(),
            'last_page' => $orders->lastPage(),
            'per_page' => $orders->perPage(),
            'total' => $orders->total(),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => $meta,
        ]);
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
   
}
