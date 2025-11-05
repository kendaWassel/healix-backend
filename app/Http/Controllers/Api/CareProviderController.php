<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CareProvider; // استيراد الموديل
use App\Models\User;         // استيراد موديل المريض (User)
use App\Models\Appointment;  // استيراد الموديل (اختياري إذا تحتاجه)

class CareProviderController extends Controller
{
    public function requests()
    {
        // جلب أول CareProvider مع الطلبات والمريض المرتبط
        $careProvider = CareProvider::with('appointments.patient')->first(); // للتجربة

        if(!$careProvider) {
            return response()->json([], 200); // لو ما في بيانات
        }

        $appointments = $careProvider->appointments->map(function($appointment) {
    return [
        'id' => $appointment->id,
        'patient_name' => $appointment->patient->full_name ?? 'N/A',
        'patient_address' => $appointment->patient->address ?? '',
        'patient_phone' => $appointment->patient->phone ?? '',
        'date' => $appointment->date,
        'time' => $appointment->time,
        'status' => $appointment->status,
        'notes' => $appointment->notes ?? '',
    ];
});


        return response()->json($appointments, 200);
    }
}
