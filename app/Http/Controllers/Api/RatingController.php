<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Doctor;
use App\Models\Rating;
use App\Models\Patient;
use App\Models\Consultation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Mockery\Matcher\Any;

class RatingController extends Controller
{
    public function rateDoctor(Request $request, int $doctorId)
{
    $validated = $request->validate([
        'consultation_id' => 'required|integer|exists:consultations,id',
        'stars' => 'required|integer|min:1|max:5',
    ]);

    try {
        DB::beginTransaction();

        // Get patient record
        $patient=Auth::user()->patient;

        if (!$patient) {
            return response()->json([
                'status' => 'error',
                'message' => 'You cannot rate because you have not booked a consultation before.'
            ], 403);
        }

        // Validate doctor exists
        $doctor = Doctor::find($doctorId);
        if (!$doctor) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Doctor not found.'
            ], 404);
        }

        // Find consultation
        $consultation = Consultation::where('id', $validated['consultation_id'])
            ->where('patient_id', $patient->id)
            ->where('doctor_id', $doctorId)
            ->where('status', 'completed')
            ->first();
        

        if (!$consultation) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Consultation not found or unauthorized.'
            ], 404);
        }

        // Must be completed
        if ($consultation->status !== 'completed') {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'You can only rate a doctor after the consultation is completed.'
            ], 422);
        }

        // Create or update rating
        $rating = Rating::updateOrCreate(
            ['consultation_id' => $consultation->id],
            [
                'doctor_id' => $doctorId,
                'patient_id' => $patient->id, // patient table ID
                'stars' => $validated['stars'],
            ]
        );

        // Update doctor average
        $avgRating = Rating::where('doctor_id', $doctorId)->avg('stars');
        $doctor->update(['rating_avg' => round($avgRating, 1)]);

        DB::commit();

        return response()->json([
            'status' => 'success',
            'message' => 'Rating submitted successfully',
            'data' => $rating
        ], 200);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to submit rating',
            'error' => $e->getMessage()
        ], 500);
    }
}

    // public function rateDoctor(Request $request, $doctorId)
    // {
    //     $validated = $request->validate([
    //         'consultation_id' => 'required|integer|exists:consultations,id',
    //         'stars' => 'required|integer|min:1|max:5',
    //     ]);
    //     try {
    //         DB::beginTransaction();

    //         // Get patient model for authenticated user
    //         $patient = Patient::where('id', Auth::id())->first();

    //         if (!$patient) {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'You cannot rate because you have not booked a consultation before.'
    //             ], 403);
    //         }

            
    //         $doctor = Doctor::find($doctorId);
    //         if (!$doctor) {
    //             DB::rollBack();
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'Doctor not found.'
    //             ], 404);
    //         }

    //         // Use patient model id (not user id) when looking up consultation
    //         $consultation = Consultation::where('id', $validated['consultation_id'])
    //             ->where('patient_id', Auth::id())
    //             ->where('doctor_id', $doctorId)
    //             ->first();

    //         if (!$consultation) {
    //             DB::rollBack();
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'Consultation not found or you do not have permission to rate this consultation'
    //             ], 404);
    //         }

    //         // Validate consultation is completed
    //         if ($consultation->status !== 'completed') {
    //             DB::rollBack();
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'You can only rate a doctor after the consultation is completed'
    //             ], 422);
    //         }

    //         // Check if consultation has already been rated
    //         $existingRating = Rating::where('consultation_id', $consultation->id)->first();

    //         if ($existingRating) {
    //             $existingRating->update(['stars' => $validated['stars']]);
    //             $rating = $existingRating;
    //         } else {
    //             $rating = Rating::create([
    //                 'consultation_id' => $consultation->id,
    //                 'doctor_id' => $consultation->doctor_id,
    //                 'patient_id' => $patient->id,
    //                 'stars' => $validated['stars'],
    //             ]);
    //         }

    //         $avgRating = Rating::where('doctor_id', $consultation->doctor_id)->avg('stars');
    //         Doctor::where('id', $consultation->doctor_id)->update(['rating_avg' => round($avgRating, 1)]);

    //         DB::commit();

    //         return response()->json([
    //             'status' => 'success',
    //             'message' => 'Rating submitted successfully',
    //             'data' => [
    //                 'rating' => $rating->stars,
    //                 'consultation_id' => $rating->consultation_id,
    //                 'doctor_id' => $rating->doctor_id,
    //                 'updated_at' => $rating->updated_at
    //             ]
    //         ], 200);

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Failed to submit rating',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }
    public function getDoctorRatings(Request $request, $doctorId)
    
    {
        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:50'
        ]);

        try {
            $doctor = Doctor::findOrFail($doctorId);
            
            $query = Rating::with('patient.user')
                ->where('doctor_id', $doctorId)
                ->orderBy('created_at', 'desc');

            $perPage = $request->get('per_page', 10);
            $ratings = $query->paginate($perPage);

            $data = $ratings->map(function ($rating) {
                return [
                    'id' => $rating->id,
                    'stars' => $rating->stars,
                    'patient_name' => $rating->patient->user->full_name,
                    'created_at' => $rating->created_at->format('Y-m-d H:i:s')
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'doctor_id' => $doctorId,
                    'average_rating' => $doctor->rating_avg,
                    'total_ratings' => $ratings->total(),
                    'ratings' => $data
                ],
                'meta' => [
                    'current_page' => $ratings->currentPage(),
                    'last_page' => $ratings->lastPage(),
                    'per_page' => $ratings->perPage(),
                    'total' => $ratings->total()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve ratings',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getMyRatingForConsultation($consultationId)
    {
        try {
            // Verify consultation belongs to authenticated patient
            $consultation = Consultation::where('id', $consultationId)
                ->where('patient_id', Auth::id())
                ->first();

            if (!$consultation) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Consultation not found or you do not have permission to view this rating'
                ], 404);
            }

            $rating = Rating::where('consultation_id', $consultationId)->first();

            if (!$rating) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No rating found for this consultation',
                    'data' => null
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'consultation_id' => $rating->consultation_id,
                    'doctor_id' => $rating->doctor_id,
                    'rating' => $rating->stars,
                    'created_at' => $rating->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $rating->updated_at->format('Y-m-d H:i:s')
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve rating',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    
     

}
