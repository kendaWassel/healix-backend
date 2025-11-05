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
        $user = Auth::user();
        $careProvider = $user->careProvider;

        if (!$careProvider || $careProvider->type !== 'physiotherapist') {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized or not a physiotherapist.'
            ], 403);
        }

        $perPage = $request->get('per_page', 10);

        $visits = HomeVisit::with('patient')
            ->where('careprovider_id', $careProvider->id)
            ->where('service_type', 'physiotherapist')
            ->whereIn('status', ['accepted', 'pending'])
            ->orderBy('scheduled_at', 'asc')
            ->paginate($perPage);

        $data = $visits->map(function ($visit) {
            return [
                'id' => $visit->visit_id,
                'patient_name' => $visit->patient->full_name ?? '',
                'service' => 'Physiotherapy Session',
                'address' => $visit->patient->address ?? '',
                'scheduled_at' => $visit->scheduled_at->toIso8601String(),
                'status' => $visit->status,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    public function orders(Request $request)
    {
        $user = Auth::user();
        $careProvider = $user->careProvider;

        if (!$careProvider || $careProvider->type !== 'physiotherapist') {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized or not a physiotherapist.'
            ], 403);
        }

        $perPage = $request->get('per_page', 10);

        $orders = HomeVisit::with('patient')
            ->where('careprovider_id', $careProvider->id)
            ->where('service_type', 'physiotherapist')
            ->whereIn('status', ['accepted', 'completed'])
            ->orderBy('scheduled_at', 'asc')
            ->paginate($perPage);

        $data = $orders->map(function ($visit) {
            return [
                'id' => $visit->visit_id,
                'patient_name' => $visit->patient->full_name ?? '',
                'service' => 'Physiotherapy Session',
                'address' => $visit->patient->address ?? '',
                'scheduled_at' => $visit->scheduled_at->toIso8601String(),
                'status' => $visit->status,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    public function accept($id)
    {
        $user = Auth::user();
        $careProvider = $user->careProvider;

        if (!$careProvider || $careProvider->type !== 'physiotherapist') {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized or not a physiotherapist.'
            ], 403);
        }

        $visit = HomeVisit::where('visit_id', $id)
            ->where('careprovider_id', $careProvider->id)
            ->where('service_type', 'physiotherapist')
            ->first();

        if (!$visit) {
            return response()->json(['status' => 'error', 'message' => 'Home visit not found.'], 404);
        }

        $visit->status = 'accepted';
        $visit->save();

        return response()->json(['status' => 'success', 'message' => 'Home visit accepted successfully']);
    }

   
}
