<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\HomeVisit;

class PhysiotherapistController extends Controller
{
    public function schedules(Request $request)
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

        $visits = HomeVisit::with('patient.user')
            ->where('care_provider_id', $careProvider->id)
            ->where('service_type', 'physiotherapist')
            ->whereIn('status', ['accepted'])
            ->orderBy('scheduled_at', 'asc')
            ->paginate($request->get('per_page', 10));

        $data = $visits->getCollection()->map(function ($visit) {
            $patient = $visit->patient;
            return [
                'id' => $visit->id,
                'patient_name' => $patient?->user?->full_name,
                'address' => $patient?->address,
                'scheduled_at' => $visit->scheduled_at->toIso8601String(),
                'status' => $visit->status,
                'service' => $visit->reason,
            ];
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

        $orders = HomeVisit::with('patient.user')
            ->where('care_provider_id', $careProvider->id)
            ->where('service_type', 'physiotherapist')
            ->whereIn('status', ['pending'])
            ->orderBy('scheduled_at', 'asc')
            ->paginate($perPage);

        $data = $orders->getCollection()->map(function ($visit) {
            $patient = $visit->patient;
            return [
                'id' => $visit->id,
                'patient_name' => $patient?->user?->full_name,
                'reason' => $visit->reason,
                'address' => $patient->address,
                'scheduled_at' => $visit->scheduled_at->toIso8601String(),
                'status' => $visit->status,
            ];
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
        $user = Auth::user();
        $careProvider = $user->careProvider;

       
        $visit = HomeVisit::where('id', $id)
            ->where('care_provider_id', $careProvider->id ?? null)
            ->where('service_type', 'physiotherapist')
            ->where('status', 'pending')
            ->first();

        if (!$visit) {
            return response()->json([
                'status' => 'error',
                'message' => 'Visit not found for this provider',
            ], 404);
        }
        if($visit->status == 'accepted'){
            return response()->json([
                'status' => 'error',
                'message' => 'This session is already accepted from another physiotherapist',
            ], 400);
        }

        $visit->status = 'accepted';
        $visit->save();
        

        return response()->json([
            'status' => 'success',
            'message' => 'Home visit accepted successfully',
            'data' => [
                'id' => $visit->id,
                'scheduled_at' => $visit->scheduled_at->toIso8601String(),
                'status' => $visit->status,
            ],
        ]);
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

        $visit = HomeVisit::where('id', $id)
            ->where('care_provider_id', $careProvider->id)
            ->where('service_type', 'physiotherapist')
            ->where('status', 'accepted')
            ->first();

        if (!$visit) {
            return response()->json([
                'status' => 'error',
                'message' => 'Visit not found or not in accepted status.',
            ], 404);
        }

        $visit->started_at = now();
        $visit->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Session started successfully',
            'data' => [
                'id' => $visit->id,
                'started_at' => $visit->started_at->toIso8601String(),
            ],
        ]);
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

        $visit = HomeVisit::where('id', $id)
            ->where('care_provider_id', $careProvider->id)
            ->where('service_type', 'physiotherapist')
            ->where('status', 'accepted')
            ->whereNotNull('started_at')
            ->first();

        if (!$visit) {
            return response()->json([
                'status' => 'error',
                'message' => 'Visit not found, not started, or not in accepted status.',
            ], 404);
        }

        $visit->ended_at = now();
        $visit->status = 'completed';
        $visit->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Session ended successfully',
            'data' => [
                'id' => $visit->id,
                'ended_at' => $visit->ended_at->toIso8601String(),
                'status' => $visit->status,
            ],
        ]);
    }
   
}
