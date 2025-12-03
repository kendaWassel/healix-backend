<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Doctor;
use App\Models\Upload;
use App\Models\Patient;
use Carbon\CarbonPeriod;
use App\Models\Consultation;
use Illuminate\Http\Request;
use App\Models\Specialization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
                    $doctor_image = asset('storage/' . $upload->file_path);
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

    $doctor = Doctor::find($doctorId);

    if (!$doctor) {
        return response()->json(['message' => 'Doctor not found'], 404);
    }

    // Use provided date or fallback to today (Y-m-d)
    $date = $request->query('date', Carbon::now()->toDateString());

    // Debug log to inspect raw stored values
    Log::debug("getAvailableSlots - doctor_id: {$doctor->id}, date: {$date}, doctor_from_raw: {$doctor->from}, doctor_to_raw: {$doctor->to}");

    // Ensure working hours exist
    if (empty($doctor->from) || empty($doctor->to)) {
        return response()->json([
            'message' => 'Doctor working hours not configured',
            'doctor_from' => $doctor->from,
            'doctor_to' => $doctor->to,
        ], 422);
    }

    // Try parsing "from" time (trying multiple formats)
    try {
        $fromTime = Carbon::createFromFormat('H:i:s', $doctor->from) 
                    ?? Carbon::createFromFormat('H:i', $doctor->from);
    } catch (\Throwable $e) {
        // Fallback to general parser
        try {
            $fromTime = Carbon::parse($doctor->from);
        } catch (\Throwable $ex) {
            return response()->json(['message' => 'Invalid doctor.from format', 'error' => $ex->getMessage()], 422);
        }
    }

    // Try parsing "to" time
    try {
        $toTime = Carbon::createFromFormat('H:i:s', $doctor->to) 
                  ?? Carbon::createFromFormat('H:i', $doctor->to);
    } catch (\Throwable $e) {
        try {
            $toTime = Carbon::parse($doctor->to);
        } catch (\Throwable $ex) {
            return response()->json(['message' => 'Invalid doctor.to format', 'error' => $ex->getMessage()], 422);
        }
    }

    // Combine date with time to build full datetime objects
    try {
        $from = Carbon::parse($date . ' ' . $fromTime->format('H:i:s'));
        $to   = Carbon::parse($date . ' ' . $toTime->format('H:i:s'));
    } catch (\Throwable $e) {
        return response()->json(['message' => 'Failed to parse from/to with date', 'error' => $e->getMessage()], 422);
    }

    // Ensure from < to
    if ($from->gte($to)) {
        return response()->json([
            'message' => 'Invalid schedule: from must be before to',
            'from' => $from->toDateTimeString(),
            'to' => $to->toDateTimeString(),
        ], 422);
    }

    // Time interval
    $interval = '30 minutes';

    // Build period
    $period = CarbonPeriod::create($from, $interval, $to);

    // Validate period generation
    if (iterator_count($period) === 0) {
        return response()->json([
            'message' => 'No time slots generated. Check from/to/interval.',
            'from' => $from->toDateTimeString(),
            'to' => $to->toDateTimeString(),
            'interval' => $interval,
        ], 422);
    }

    $availableSlots = [];

    foreach ($period as $slot) {
        // Skip if slot equals or exceeds the end time
        if ($slot->gte($to)) {
            continue;
        }

        // Skip past time slots only for today's date
        $todayString = Carbon::now()->toDateString();
        if ($date === $todayString && $slot->isPast()) {
            continue;
        }

        $availableSlots[] = [
            'time' => $slot->format('H:i'),
            'is_available' => true,
        ];
    }

    return response()->json([
        'doctor_id' => $doctor->id,
        'doctor_name' => $doctor->user?->full_name,
        'date' => $date,
        'available_slots' => array_values($availableSlots),
    ], 200);
}


    public function getDoctorSchedules(Request $request)
    {
        $validated = $request->validate([
            'status' => 'sometimes|string|in:pending,scheduled,in_progress,completed,cancelled',
            'date' => 'sometimes|date',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'latest' => 'sometimes|boolean',
        ]);

        // Get authenticated user's doctor record
        $user = Auth::user();
        $doctor = $user?->doctor;

        if (!$doctor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Doctor profile not found. Please complete your doctor profile.',
            ], 404);
        }

        $doctorId = $doctor->id;

        // Eager-load patient -> user so we can return patient details without N+1
        $query = Consultation::with(['patient'])
            ->where('doctor_id', $doctorId);

        if ($request->filled('status')) {
            $query->where('status', $validated['status']);
        }

        if ($request->filled('date')) {
            $date = Carbon::parse($validated['date'])->toDateString();
            $query->whereDate('scheduled_at', $date);
        }

        // If client asks for latest (non-paginated), return most recent N results
        if ($request->boolean('latest')) {
            $limit = (int) $request->get('per_page', 5);
            $consultations = $query->orderByDesc('scheduled_at')->take($limit)->get();

            $data = $consultations->map(function ($consultation) {
                $patient = $consultation->patient;
                $patientUser = $patient?->user;

                return [
                    'consultation_id' => $consultation->id,
                    'patient_id' => $patient?->id,
                    'patient_name' => $patientUser?->full_name ?? null,
                    'patient_phone' => $patientUser?->phone ?? null,
                    'status' => $consultation->status,
                    'type' => $consultation->type,
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

        // Default paginated response
        $perPage = $request->get('per_page', 10);
        $consultations = $query->orderBy('scheduled_at', 'asc')->paginate($perPage)->appends($request->query());
        // dd($consultations);
        
        $data = $consultations->getCollection()->map(function ($consultation) {

            $patient = Patient::find($consultation->patient_id);
            $user = $patient?->user; 

            return [
                'consultation_id' => $consultation->id,
                'patient_id'      => $patient?->id,
                'patient_name'    => $user?->full_name,
                'patient_phone'   => $user?->phone ,
                'status'          => $consultation->status,
                'type'            => $consultation->type,
                'scheduled_at'    => optional($consultation->scheduled_at)->format('Y-m-d H:i'),
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
 
    



}
    





