<?php

namespace App\Http\Controllers\Api\CareProvider;

use App\Services\NurseService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class NurseController extends Controller
{
    protected $nurseService;

    public function __construct(NurseService $nurseService)
    {
        $this->nurseService = $nurseService;
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

        if (!$careProvider || $careProvider->type !== 'nurse') {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized or not a nurse.'
            ], 403);
        }

        $filters = [];
        if ($request->has('status')) {
            $filters['status'] = $request->status;
        }

        $visits = $this->nurseService->getSchedules($filters, $request->get('per_page', 10));
        
        $data = $visits->getCollection()->map(function ($visit) {
            return $this->nurseService->formatScheduleData($visit);
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

        if (!$careProvider || $careProvider->type !== 'nurse') {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized or not a nurse.'
            ], 403);
        }

        $perPage = $request->get('per_page', 10);
        $orders = $this->nurseService->getOrders($perPage);

        $data = $orders->getCollection()->map(function ($visit) {
            return $this->nurseService->formatOrderData($visit);
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
            $visit = $this->nurseService->acceptOrder($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Home visit accepted successfully',
                'data' => $visit,
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

        if (!$careProvider || $careProvider->type !== 'nurse') {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized or not a nurse.'
            ], 403);
        }

        try {
            $visit = $this->nurseService->startSession($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Session started successfully',
                'data' => [
                    'id' => $visit->id,
                    'started_at' => $visit->started_at->toIso8601String(),
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

        if (!$careProvider || $careProvider->type !== 'nurse') {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized or not a nurse.'
            ], 403);
        }

        try {
            $visit = $this->nurseService->endSession($id);

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
