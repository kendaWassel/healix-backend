<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\Doctor;
use App\Models\Upload;
use App\Models\Patient;
use Carbon\CarbonPeriod;
use App\Models\Consultation;
use Illuminate\Http\Request;
use App\Models\Specialization;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class DoctorController extends Controller
{
    public function getDoctorsBySpecialization(Request $request)
    {
        $validated = $request->validate([
            'specialization_id' => 'required|integer|exists:specializations,id',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $specialization = Specialization::find($validated['specialization_id']);

        $query = Doctor::with(['user', 'ratings', 'specialization'])
            ->where('specialization_id', $specialization->id)
            ->withAvg('ratings as ratings_avg', 'stars')
            ->orderByDesc('ratings_avg');

        // Pagination
        $perPage = $request->get('per_page', 10);
        $doctorsPaginated = $query->paginate($perPage)->appends($request->query());

        // Check if no doctors found
        if ($doctorsPaginated->isEmpty()) {
            return response()->json([
                'status' => 'empty',
                'message' => 'No doctors available for this specialization yet.',
                'specialization' => $specialization->name,
                'data' => [],
            ], 200);
        }

        $doctor = $doctorsPaginated->getCollection()->map(function ($doctor) {
            $rating = $doctor->rating_avg ?? round($doctor->ratings->avg('stars') ?? 0, 1);
            $doctor_image = null;
            if ($doctor->doctor_image_id) {
                $upload = Upload::find($doctor->doctor_image_id);
                if ($upload && $upload->file_path) {
                    $doctor_image = asset('storage/' . ltrim($upload->file_path, '/'));
                }
            }

                
            
            //get specialization name
                $specializationName = $doctor->specialization ? $doctor->specialization->name : null;
            return [
                'id' => $doctor->id,
                'name' => $doctor->user?->full_name ? 'Dr. ' . $doctor->user->full_name : null,
                'rating' => (float) number_format($rating, 1),
                'consultation_fee' => (float) $doctor->consultation_fee,
                'available_from' => $doctor->from,
                'available_to' => $doctor->to,
                'doctor_image' => $doctor_image,
                'specialization' => $specializationName,
            ];
        })->values();

        $pager = [
            'current_page' => $doctorsPaginated->currentPage(),
            'last_page' => $doctorsPaginated->lastPage(),
            'per_page' => $doctorsPaginated->perPage(),
            'total' => $doctorsPaginated->total()
        ];

        return response()->json([
            'status' => 'success',
            'data' => $doctor,
            'meta' => $pager,
        ], 200);
    }

    public function getAvailableSlots(Request $request, $doctorId)
    {
        $validated = $request->validate([
            'date' => 'sometimes|date',
        ]);

        $doctor = Doctor::findOrFail($doctorId);

        if (!$doctor) {
            return response()->json([
                'message' => 'Doctor not found'
            ], 404);
        }

        $date = $request->query('date', Carbon::today()->toDateString());

        
        $interval = '30 minutes';

        $from = Carbon::parse($date . ' ' . $doctor->from);
        $to   = Carbon::parse($date . ' ' . $doctor->to);

        $period = CarbonPeriod::create($from, $interval, $to);

        $availableSlots = [];

        foreach ($period as $slot) {
            $formatted = $slot->format('H:i');

            if ($date == Carbon::today()->toDateString() && $slot->isPast()) {
                continue;
            }

            $availableSlots[] = [
                'time' => $formatted,
                'is_available' => true
            ];
        }

        return response()->json([
            'doctor_id' => $doctor->id,
            'doctor_name' => $doctor->user?->full_name,
            'date' => $date,
            'available_slots' => $availableSlots
        ], 200);
    }


    public  function getDoctorSchedules(Request $request){

        $validated = $request->validate([
            'status' => 'sometimes|string|in:pending,in_progress,completed,cancelled',
            'date' => 'sometimes|date',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $doctorId = Auth::id();

        $query = Consultation::with(['patient.user'])
            ->where('doctor_id', $doctorId)
            ->orderBy('scheduled_at', 'asc');

        if ($request->filled('status')) {
            $query->where('status', $validated['status']);
        }

        if ($request->filled('date')) {
            $date = Carbon::parse($validated['date'])->toDateString();
            $query->whereDate('scheduled_at', $date);
        }

        $perPage = $request->get('per_page', 10);
        $consultations = $query->paginate($perPage)->appends($request->query());

        $data = $consultations->getCollection()->map(function ($consultation) {
            $patient = $consultation->patient?->user;

            return [
                'id' => $consultation->id,
                'patient_name' => $patient->full_name,
                'patient_phone' => $patient->phone,
                'scheduled_at' => optional($consultation->scheduled_at)->format('Y-m-d H:i'),
                'status' => $consultation->status,
                'call_type' => $consultation->call_type,
                'consultation_fee' => $consultation->doctor?->consultation_fee,
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => [
                'current_page' => $consultations->currentPage(),
                'last_page' => $consultations->lastPage(),
                'per_page' => $consultations->perPage(),
                'total' => $consultations->total(), 
            ],
        ], 200);
    }

    
    public function viewPatientMedicalRecored($patientId){
        $doctor = Auth::user()->doctor;
        if (!$doctor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access – only doctors can view medical records.'
            ], 403);
        }

            $patient = Patient::with(['user', 'medicalRecord.attachments'])->find($patientId);

            if (!$patient) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Patient not found.'
                ], 404);
            }
        $record = $patient->medicalRecord;

            return response()->json([
                'status' => 'success',
                'data' => [
                    'patient_id' => $patient->id,
                    'patient_name' => $patient->user->full_name,
                    'gender' => $patient->user->gender,
                    'birth_date' => $patient->user->birth_date,
                    'address' => $patient->user->address,
                    'medical_record' => [
                        'diagnosis' => $record->diagnosis ?? null,
                        'treatment_plan' => $record->treatment_plan ?? null,
                        'chronic_diseases' => $record->chronic_diseases ?? null,
                        'previous_surgeries' => $record->previous_surgeries ?? null,
                        'allergies' => $record->allergies ?? null,
                        'current_medications' => $record->current_medications ?? null,
                        'attachments' => $record->attachments->map(function ($file) {
                            return [
                                'id' => $file->id,
                                'file_name' => basename($file->file_path),
                                'file_url' => asset('storage/' . ltrim($file->file_path, '/')),
                            ];
                        })
                    ]
                ]
            ], 200);
    }

}
    





