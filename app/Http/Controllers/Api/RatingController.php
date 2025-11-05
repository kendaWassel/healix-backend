<?php

namespace App\Http\Controllers\Api;

use App\Models\Rating;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class RatingController extends Controller
{
    public function rateDoctor(Request $request)
    {
        $validated = $request->validate([
            'doctor_id' => 'required|exists:doctors,id',
            'stars' => 'required|integer|min:1|max:5'
        ]);

        try {
            DB::beginTransaction();

            // Get the authenticated user's patient record
            $patient = Patient::where('user_id', Auth::id())->firstOrFail();
            
            // Check if patient has already rated this doctor
            $existingRating = Rating::where('doctor_id', $validated['doctor_id'])
                ->where('patient_id', $patient->id)
                ->first();

            if ($existingRating) {
                // Update existing rating
                $existingRating->update([
                    'stars' => $validated['stars'],
                    'comment' => $validated['comment'] ?? null
                ]);
                $rating = $existingRating;
            } else {
                // Create new rating
                $rating = Rating::create([
                    'doctor_id' => $validated['doctor_id'],
                    'patient_id' => $patient->id,
                    'stars' => $validated['stars'],
                ]);
            }

            // Update doctor's average rating
            $avgRating = Rating::where('doctor_id', $validated['doctor_id'])
                ->avg('stars');
            
            Doctor::where('id', $validated['doctor_id'])
                ->update(['rating_avg' => round($avgRating, 1)]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Rating submitted successfully',
                'data' => [
                    'rating' => $rating->stars,
                    'doctor_id' => $rating->doctor_id,
                    'updated_at' => $rating->updated_at
                ]
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
                    'comment' => $rating->comment,
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

    
    // Get current patient's rating for a specific doctor
     
    public function getMyRatingForDoctor($doctorId)
    {
        try {
            $patient = Patient::where('user_id', Auth::id())->firstOrFail();
            
            $rating = Rating::where('doctor_id', $doctorId)
                ->where('patient_id', $patient->id)
                ->first();

            if (!$rating) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No rating found',
                    'data' => null
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
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