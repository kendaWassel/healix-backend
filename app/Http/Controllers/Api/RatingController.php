<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Doctor;
use App\Models\Rating;
use App\Models\Patient;
use App\Models\Pharmacist;
use App\Models\Consultation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
 

class RatingController extends Controller
{
    public function rateDoctor(Request $request, int $doctorId)
    {
        $validated = $request->validate([
            'consultaion_id' => 'required',
            'stars' => 'required|integer|min:1|max:5',
        ]);

        try {
            DB::beginTransaction();

            $userId = Auth::id();
            if (!$userId) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthenticated.'
                ], 401);
            }

            $patient = Patient::where('user_id', $userId)->first();
            if (!$patient) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Patient profile not found.'
                ], 404);
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

            // Create or update rating
            $rating = Rating::updateOrCreate(
                ['user_id' => $userId, 'target_type' => 'doctor', 'target_id' => $doctorId],
                ['stars' => $validated['stars']]
            );

            // Update doctor average
            $avgRating = Rating::where('target_type', 'doctor')->where('target_id', $doctorId)->avg('stars');
            $doctor->update(['rating_avg' => round($avgRating, 1)]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Rating submitted successfully',
                'data' => [
                    'rating' => $rating->stars,
                    'target_type' => $rating->target_type,
                    'target_id' => $rating->target_id,
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
            
            $query = Rating::with('user')
                ->where('target_type', 'doctor')
                ->where('target_id', $doctorId)
                ->orderBy('created_at', 'desc');

            $perPage = $request->get('per_page', 10);
            $ratings = $query->paginate($perPage);

            $data = $ratings->map(function ($rating) {
                return [
                    'id' => $rating->id,
                    'stars' => $rating->stars,
                    'user_name' => $rating->user->full_name ?? 'Unknown',
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
    public function ratePharmacy(Request $request, int $pharmacyId)
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
        ]);

        try {
            DB::beginTransaction();

            $userId = Auth::id();
            if (!$userId) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthenticated.'
                ], 401);
            }

            $patientModel = Auth::user()->patient;
            if (!$patientModel) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Patient profile not found.'
                ], 404);
            }

            // Create or update rating
            $rating = Rating::updateOrCreate(
                ['user_id' => $userId, 'target_type' => 'pharmacist', 'target_id' => $pharmacyId],
                ['stars' => $validated['rating']]
            );

            // Update pharmacist average
            $avgRating = Rating::where('target_type', 'pharmacist')->where('target_id', $pharmacyId)->avg('stars');
            $pharmacist = Pharmacist::find($pharmacyId);
            if ($pharmacist) {
                $pharmacist->update(['rating_avg' => round($avgRating, 1)]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'rating_id' => $rating->id
                ],
                'message' => 'Rating submitted'

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

    
     

}
