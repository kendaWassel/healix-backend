<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\HomeVisit;

class CareProviderController extends Controller
{
    public function schedules(Request $request)
    {
        // المستخدم المسجل دخول
        $user = Auth::user();

        // سجل care_provider المرتبط بالمستخدم
        $careProvider = $user->careProvider;

        if (!$careProvider) {
            return response()->json([
                'status' => 'error',
                'message' => 'Care provider not found for this user.'
            ], 404);
        }

        $perPage = $request->get('per_page', 10);

        // جلب الزيارات المرتبطة بالمستخدم ونوع الخدمة الخاص به
        $visits = HomeVisit::with('patient')
            ->where('careprovider_id', $careProvider->id)
            ->where('service_type', $careProvider->type)
            ->whereIn('status', ['accepted', 'pending'])
            ->orderBy('scheduled_at', 'asc')
            ->paginate($perPage);

        // تجهيز البيانات للرد
        $data = $visits->map(function($visit) {
            return [
                'id' => $visit->visit_id,
                'patient_name' => $visit->patient->full_name,
                'service' => ucfirst($visit->service_type),
                'address' => $visit->patient->address ?? '',
                'scheduled_at' => $visit->scheduled_at->toIso8601String(),
                'status' => $visit->status,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'pager' => [
                'current_page' => $visits->currentPage(),
                'total' => $visits->total(),
            ],
        ]);
    }
    
}
