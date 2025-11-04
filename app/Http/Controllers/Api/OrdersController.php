<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\HomeVisit;

class OrdersController extends Controller
{
    /**
     * View My Orders (Accepted or Completed)
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $careProvider = $user->careProvider;

        if (!$careProvider) {
            return response()->json([
                'status' => 'error',
                'message' => 'Care provider not found for this user.'
            ], 404);
        }

        $perPage = $request->get('per_page', 10);

        $orders = HomeVisit::with('patient')
            ->where('careprovider_id', $careProvider->id)
            ->where('service_type', $careProvider->type)
            ->whereIn('status', ['accepted', 'completed', 'pending']) // تأكد أننا نعرض pending أيضاً
            ->orderBy('scheduled_at', 'asc')
            ->paginate($perPage);

        $data = $orders->map(function ($visit) {
            return [
                'id' => $visit->visit_id,
                'patient_name' => $visit->patient->full_name,
                'service' => ucfirst($visit->service_type) . ' Session',
                'address' => $visit->patient->address ?? '',
                'scheduled_at' => $visit->scheduled_at->toIso8601String(),
                'status' => $visit->status,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'pager' => [
                'current_page' => $orders->currentPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Accept Request
     */
    public function accept($id)
    {
        $user = Auth::user();
        $careProvider = $user->careProvider;

        $visit = HomeVisit::where('visit_id', $id)
            ->where('careprovider_id', $careProvider->id)
            ->first();

        if (!$visit) {
            return response()->json([
                'status' => 'error',
                'message' => 'Home visit not found.'
            ], 404);
        }

        $visit->status = 'accepted';
        $visit->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Home visit accepted successfully',
        ]);
    }

    /**
     * Reject Request
     */
    public function reject($id)
    {
        $user = Auth::user();
        $careProvider = $user->careProvider;

        $visit = HomeVisit::where('visit_id', $id)
            ->where('careprovider_id', $careProvider->id)
            ->first();

        if (!$visit) {
            return response()->json([
                'status' => 'error',
                'message' => 'Home visit not found.'
            ], 404);
        }

        $visit->status = 'rejected';
        $visit->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Home visit rejected successfully',
        ]);
    }
}
