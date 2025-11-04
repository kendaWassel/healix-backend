<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\Doctor;
use App\Models\Upload;
use Carbon\CarbonPeriod;
use App\Models\Consultation;
use Illuminate\Http\Request;
use App\Models\Specialization;
use App\Http\Controllers\Controller;

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

        $query = Doctor::with(['user', 'ratings'])
            ->where('specialization_id', $specialization->id);
            // ->withAvg('ratings', 'stars')
            // ->orderByDesc('ratings_avg');

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

        // Map data
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
        //store available slots in database
        // $consultation = Consultation::create([
        //     'doctor_id' => $doctor->id,
        //     'schul' => $availableSlots
        // ]);

        return response()->json([
            'doctor_id' => $doctor->id,
            'doctor_name' => $doctor->user?->full_name,
            'date' => $date,
            'available_slots' => $availableSlots
        ], 200);
    }

}
    





