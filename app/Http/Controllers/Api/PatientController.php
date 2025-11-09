<?php

namespace App\Http\Controllers\Api;

use App\Models\Upload;
use App\Models\Consultation;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class PatientController extends Controller
{
    public function updateProfile()
    {
        //
    }
    public function rateService(Request $request)
    {
        //
    }
        public function getPatientScheduledConsultations(Request $request)
    {

        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        try {
            $perPage = $request->get('per_page', 10);
            $consultations = Consultation::where('patient_id', Auth::id())->paginate($perPage)->appends($request->query());
            if ($consultations->isEmpty()) {
                return response()->json([
                    'status' => 'empty',
                    'message' => 'No scheduled consultations found',
                    'data' => []
                ], 200);
            }
            $consultations->getCollection()->map(function ($consultation) {
                $doctor = $consultation->doctor;
                $doctorImage = null;
                if ($doctor && !empty($doctor->doctor_image_id)) {
                    $upload = Upload::find($doctor->doctor_image_id);
                    if ($upload && $upload->file_path) {
                        $doctorImage = asset('storage/' . ltrim($upload->file_path, '/'));
                    }
                }

                return [
                    'id' => $consultation->id,
                    'doctor_id' => $doctor ? $doctor->id : null,
                    'doctor_name' => $doctor ? 'Dr. ' . $doctor->user?->full_name : null,
                    'doctor_image' => $doctorImage,
                    'type' => $consultation->call_type,
                    'scheduled_at' => $consultation->scheduled_at ? $consultation->scheduled_at->toIso8601String() : null,
                    'status' => $consultation->status,
                ];
            });
            $meta = [
                'current_page' => $consultations->currentPage(),
                'last_page' => $consultations->lastPage(),
                'per_page' => $consultations->perPage(),
                'total' => $consultations->total(),
            ];

            return response()->json([
                'status' => 'success',
                'data' => $consultations->getCollection()->toArray(),
                'meta' => $meta
            ], 200);


            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve scheduled consultations',
                'error' => $e->getMessage()
            ], 500);
        }
    }
 

        
    
    
}
