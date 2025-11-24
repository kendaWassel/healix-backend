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
use Illuminate\Support\Facades\DB;
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
            'status' => 'sometimes|string|in:pending,scheduled,in_progress,completed,cancelled',
            'date' => 'sometimes|date',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        // Get the authenticated user's doctor record
        $user = Auth::user();
        $doctor = $user->doctor;

        if (!$doctor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Doctor profile not found. Please complete your doctor profile.',
            ], 404);
        }

        $doctorId = $doctor->id;

        $query = Consultation::with(['patient', 'doctor'])
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
            $patient = $consultation->patient;

            return [
                'id' => $consultation->id,
                'patient_name' => $patient?->full_name,
                'scheduled_at' => optional($consultation->scheduled_at)->format('Y-m-d H:i'),
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
    public function viewDetails($patientId){
        $doctor = Auth::user()->doctor;
        if (!$doctor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access - only doctors can view medical records.'
            ], 403);
        }

            $patient = Patient::with(['user', 'medicalRecord.attachments'])->find($patientId);

            if (!$patient) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Patient not found.'
                ], 404);
            }
        $record = $patient->medicalRecords;

            return response()->json([
                'status' => 'success',
                'data' => [
                    'patient_id' => $patient->id,
                    'patient_name' => $patient->user->full_name,
                    'gender' => $patient->user->gender,
                    'birth_date' => $patient->user->birth_date,
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

    public function requestHomeVisit(Request $request)
    {
        $validated = $request->validate([
            'consultation_id' => 'required|exists:consultations,id',
            'patient_id' => 'required|exists:patients,id',
            'service_type' => 'required|in:nurse,physio',
            'reason' => 'nullable|string|max:255',
            'scheduled_at' => 'required|date',
            'address' => 'required|string|max:255'
        ]);

        $doctor = auth()->user()->doctor;

        if (!$doctor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Not authorized.'
            ], 403);
        }

        // Ensure consultation belongs to the doctor
        $consultation = Consultation::where('id', $validated['consultation_id'])
            ->where('doctor_id', $doctor->id)
            ->first();

        if (!$consultation) {
            return response()->json([
                'status' => 'error',
                'message' => 'Consultation does not belong to this doctor.'
            ], 403);
        }

        $homeVisit = HomeVisit::create([
            'consultation_id' => $validated['consultation_id'],
            'patient_id' => $validated['patient_id'],
            'service_type' => $validated['service_type'],
            'reason' => $validated['reason'],
            'scheduled_at' => $validated['scheduled_at'],
            'address' => $validated['address'],
            'status' => 'pending'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Home visit request created successfully.',
            'data' => $homeVisit
        ]);
    }

    //notifications 
    // public function getNotifications(){
    //     $user = auth()->user();
    //     $notifications = $user->doctor->notifications()->get();
    //     return response()->json([
    //         'status' => 'success',
    //         'data' => $notifications
    //     ]);
    // }   



}
    





