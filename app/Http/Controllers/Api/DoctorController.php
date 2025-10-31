<?php

namespace App\Http\Controllers\Api;
use App\Models\Doctor;

use Illuminate\Http\Request;
use App\Models\Specialization;
use App\Http\Controllers\Controller;

class DoctorController extends Controller
{
    public function getDoctorsBySpecialization($specializationId)
    {
        $specialization = Specialization::find($specializationId);
        if (!$specialization) {
            return response()->json(['status' => 'error', 'message' => 'Specialization not found'], 404);
        }

        $doctors = Doctor::with('user')
            ->where('specialization_id', $specializationId)
            ->get()
            ->map(function ($doctor) {
                return [
                    'id' => $doctor->id,
                    'user_id' => $doctor->user_id,
                    'full_name' => $doctor->user?->full_name,
                    'consultation_fee' => $doctor->consultation_fee,
                    'rating_avg' => $doctor->rating_avg,
                    'from' => $doctor->from,
                    'to' => $doctor->to,
                ];
            });

        return response()->json([
            'status' => 'success', 
            'data' => $doctors
        ], 200);
    }
    
}
