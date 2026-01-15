<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Doctor;
use App\Models\Upload;
use App\Models\Patient;
use Carbon\CarbonPeriod;
use App\Models\Medication;
use App\Models\Consultation;
use App\Models\Prescription;
use App\Models\Specialization;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\PrescriptionMedication;
use Illuminate\Support\Facades\Storage;

class DoctorService
{
    public function getDoctorsBySpecialization(int $specializationId, int $perPage = 10): array
    {
        $specialization = Specialization::find($specializationId);
        if (!$specialization) {
            throw new \Exception('Specialization not found', 404);
        }

        $query = Doctor::with(['user', 'specialization'])
            ->where('specialization_id', $specialization->id)
            ->orderByDesc('rating_avg');

        $doctorsPaginated = $query->paginate($perPage);

        if ($doctorsPaginated->isEmpty()) {
            return [
                'specialization' => $specialization->name,
                'doctors' => [],
                'meta' => [
                    'current_page' => $doctorsPaginated->currentPage(),
                    'last_page' => $doctorsPaginated->lastPage(),
                    'per_page' => $doctorsPaginated->perPage(),
                    'total' => $doctorsPaginated->total()
                ],
            ];
        }

        $doctors = $doctorsPaginated->getCollection()->map(function ($doctor) {
            return $this->formatDoctorData($doctor);
        })->values();

        return [
            'specialization' => $specialization->name,
            'doctors' => $doctors,
            'meta' => [
                'current_page' => $doctorsPaginated->currentPage(),
                'last_page' => $doctorsPaginated->lastPage(),
                'per_page' => $doctorsPaginated->perPage(),
                'total' => $doctorsPaginated->total()
            ],
        ];
    }


    public function formatDoctorData(Doctor $doctor): array
    {
        $rating = $doctor->rating_avg ?? round($doctor->ratings->avg('stars') ?? 0, 1);

        $doctorImage = null;
        if ($doctor && !empty($doctor->doctor_image_id)) {
            $upload = Upload::find($doctor->doctor_image_id);
            if ($upload && $upload->file_path) {
                $doctorImage = asset('storage/' . ltrim($upload->file_path, '/'));
            }
        }

        $specializationName = $doctor->specialization ? $doctor->specialization->name : null;
        
        return [
            'id' => $doctor->id,
            'name' => $doctor->user?->full_name ? 'Dr. ' . $doctor->user->full_name : null,
            'rating' => (float) number_format($rating, 1),
            'consultation_fee' => (float) $doctor->consultation_fee,
            'available_from' => $doctor->from,
            'available_to' => $doctor->to,
            'doctor_image' => $doctorImage,
            'specialization' => $specializationName,
        ];
    }

    public function getAvailableSlots(int $doctorId, ?string $date = null): array
    {
        $doctor = Doctor::find($doctorId);
        if (!$doctor) {
            throw new \Exception('Doctor not found', 404);
        }

        // Use provided date or fallback to today (Y-m-d)
        $date = $date ?? Carbon::now()->toDateString();

        // Debug log to inspect raw stored values
        Log::debug("getAvailableSlots - doctor_id: {$doctor->id}, date: {$date}, doctor_from_raw: {$doctor->from}, doctor_to_raw: {$doctor->to}");

        // Ensure working hours exist
        if (empty($doctor->from) || empty($doctor->to)) {
            throw new \Exception('Doctor working hours not configured', 422);
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
                throw new \Exception('Invalid doctor.from format: ' . $ex->getMessage(), 422);
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
                throw new \Exception('Invalid doctor.to format: ' . $ex->getMessage(), 422);
            }
        }

        // Combine date with time to build full datetime objects
        try {
            $from = Carbon::parse($date . ' ' . $fromTime->format('H:i:s'));
            $to   = Carbon::parse($date . ' ' . $toTime->format('H:i:s'));
        } catch (\Throwable $e) {
            throw new \Exception('Failed to parse from/to with date: ' . $e->getMessage(), 422);
        }

        // Ensure from < to
        if ($from->gte($to)) {
            throw new \Exception('Invalid schedule: from must be before to', 422);
        }

        // Time interval
        $interval = '30 minutes';

        // Build period
        $period = CarbonPeriod::create($from, $interval, $to);

        // Validate period generation
        if (iterator_count($period) === 0) {
            throw new \Exception('No time slots generated. Check from/to/interval.', 422);
        }

        // Get booked slots for the date (normalized to 'H:i')
        $bookedSlots = Consultation::where('doctor_id', $doctor->id)
            ->whereDate('scheduled_at', $date)
            ->where('status', '!=', 'cancelled')
            ->pluck('scheduled_at')
            ->map(function ($dt) {
                try {
                    return Carbon::parse($dt)->format('H:i');
                } catch (\Throwable $e) {
                    return null;
                }
            })
            ->filter()
            ->values()
            ->toArray();

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

            $slotTime = $slot->format('H:i');

            // Check if slot is already booked
            if (!in_array($slotTime, $bookedSlots)) {
                $availableSlots[]= $slotTime;
            }

        }

        return [
            'doctor_id' => $doctor->id,
            'doctor_name' => $doctor->user?->full_name,
            'date' => $date,
            'available_slots' => array_values($availableSlots),
        ];
    }

    public function getDoctorSchedules(array $filters = [], int $perPage = 10, bool $latest = false)
    {
        $user = Auth::user();
        $doctor = $user?->doctor;

        if (!$doctor) {
            throw new \Exception('Doctor profile not found. Please complete your doctor profile.', 404);
        }

        $doctorId = $doctor->id;

        // Eager-load patient -> user so we can return patient details without N+1
        $query = Consultation::with(['patient'])
            ->where('doctor_id', $doctorId);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date'])) {
            $date = Carbon::parse($filters['date'])->toDateString();
            $query->whereDate('scheduled_at', $date);
        }

        // If client asks for latest (non-paginated), return most recent N results
        if ($latest) {
            $limit = $perPage;
            $consultations = $query->orderByDesc('scheduled_at')->take($limit)->get();

            $data = $consultations->map(function ($consultation) {
                return $this->formatScheduleData($consultation);
            })->values();

            return [
                'data' => $data,
                'meta' => [
                    'total' => $consultations->count(),
                ],
            ];
        }

        // Default paginated response
        $consultations = $query->orderBy('scheduled_at', 'asc')->paginate($perPage);

        $data = $consultations->getCollection()->map(function ($consultation) {
            return $this->formatScheduleData($consultation);
        })->values();

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $consultations->currentPage(),
                'last_page' => $consultations->lastPage(),
                'per_page' => $consultations->perPage(),
                'total' => $consultations->total(),
            ],
        ];
    }

    public function formatScheduleData(Consultation $consultation): array
    {
        $patient = Patient::find($consultation->patient_id);
        $user = $patient?->user;

        return [
            'consultation_id' => $consultation->id,
            'patient_id' => $patient?->id,
            'patient_name' => $user?->full_name,
            'patient_phone' => $user?->phone,
            'status' => $consultation->status,
            'type' => $consultation->type,
            'scheduled_at' => optional($consultation->scheduled_at)->format('Y-m-d H:i'),
        ];
    }

    public function createPrescription(array $validated): Prescription
    {
        $doctor = Auth::user()?->doctor;

        if (!$doctor) {
            throw new \Exception('Unauthorized - only doctors can create prescriptions.', 403);
        }

        $consultation = Consultation::where('id', $validated['consultation_id'])
            ->where('doctor_id', $doctor->id)
            ->first();

        if (!$consultation) {
            throw new \Exception('Consultation not found for this doctor.', 404);
        }

        if ($consultation->status !== 'completed') {
            throw new \Exception('Only completed consultations can have prescriptions.', 400);
        }

        $prescription = Prescription::create([
            'consultation_id' => $consultation->id,
            'patient_id' => $consultation->patient_id,
            'doctor_id' => $doctor->id,
            'diagnosis' => $validated['diagnosis'],
            'notes' => $validated['notes'] ?? null,
            'source' => 'doctor_written',
            'status' => 'created',
        ]);

        foreach ($validated['medicines'] as $med) {
            // Try to resolve medication from catalog 
            $medication = Medication::firstOrCreate(
                [
                    'name' => $med['name'],
                    'dosage' => $med['dosage'],
                ],
                [
                    'name' => $med['name'],
                    'dosage' => $med['dosage'],
                ]
            );

            PrescriptionMedication::create([
                'prescription_id' => $prescription->id,
                'medication_id' => $medication->id,
                'boxes' => $med['boxes'],
                'instructions' => $med['instructions'] ?? null,
            ]);
        }

        return $prescription;
    }
}
