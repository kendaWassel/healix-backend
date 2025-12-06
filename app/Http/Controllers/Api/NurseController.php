<?php

namespace App\Http\Controllers\Api;

use App\Models\HomeVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class NurseController extends Controller
{
    public function schedules(Request $request)
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

        $visits = HomeVisit::with('patient.user')
            ->where('careprovider_id', $careProvider->id)
            ->where('service_type', 'nurse')
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
                'service' => $visit->service,
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

        if (!$careProvider || $careProvider->type !== 'nurse') {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized or not a nurse.'
            ], 403);
        }

        $perPage = $request->get('per_page', 10);

        $orders = HomeVisit::with('patient.user')
            ->where('care_provider_id', $careProvider->id)
            ->where('service_type', 'nurse')
            ->whereIn('status', ['pending'])
            ->orderBy('scheduled_at', 'asc')
            ->paginate($perPage);

        $data = $orders->getCollection()->map(function ($visit) {
            $patient = $visit->patient;
            return [
                'id' => $visit->id,
                'patient_name' => $patient?->user?->full_name,
                'service' => $visit->service,
                'address' => $patient?->address,
                'status' => $visit->status,
                'scheduled_at' => $visit->scheduled_at->toIso8601String(),
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
            ->where('service_type', 'nurse')
            ->where('status', 'pending')
            ->first();

        if (!$visit) {
            return response()->json([
                'status' => 'error',
                'message' => 'Visit not found for this provider',
            ], 404);
        }

        $visit->status = 'accepted';
        $visit->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Home visit accepted successfully',
            'data' => $visit,
        ]);
    }



    
}
