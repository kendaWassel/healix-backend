<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Order;
use App\Models\Doctor;
use App\Models\Rating;
use App\Models\Patient;
use App\Models\Delivery;
use App\Models\HomeVisit;
use App\Models\Pharmacist;
use App\Models\CareProvider;
use App\Models\Consultation;
use App\Models\DeliveryTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
 

class RatingController extends Controller
{
    public function rateDoctor(Request $request, int $consultationId, int $doctorId)
    {
        $validated = $request->validate([
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

            // Validate consultation exists and belongs to patient and doctor
            $consultation = Consultation::where('id', $consultationId)
                ->where('patient_id', $patient->id)
                ->where('doctor_id', $doctorId)
                ->where('status', 'completed')
                ->first();

            if (!$consultation) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'you can only rate doctors after completing a consultation with them.'
                ], 403);
            }

            // Create or update rating
            $rating = Rating::updateOrCreate(
                ['user_id' => $userId, 'target_type' => 'doctor', 'target_id' => $doctorId],
                [
                    'stars' => $validated['stars'],
                    'consultation_id' => $consultationId
                ]
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

    public function rateDelivery(Request $request, int $taskId, int $deliveryId)
    {
        $validated = $request->validate([
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

            // Validate delivery task exists, belongs to delivery, and order belongs to patient
            $deliveryTask = DeliveryTask::with('order')
                ->where('id', $taskId)
                ->where('delivery_id', $deliveryId)
                ->first();             

            if (!$deliveryTask) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Delivery task not found.'
                ], 404);
            }

            // Validate that the order belongs to the patient
            if (!$deliveryTask->order || $deliveryTask->order->patient_id !== $patient->id) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'You can only rate deliveries for your own orders.'
                ], 403);
            }

            // Check if task is complete
            if ($deliveryTask->status !== 'delivered') {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'You can only rate deliveries that have been delivered.'
                ], 403);
            }

            // Validate delivery exists
            $delivery = Delivery::find($deliveryId);
            if (!$delivery) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Delivery not found.'
                ], 404);
            }

            // Create or update rating
            $rating = Rating::updateOrCreate(
                ['user_id' => $userId, 'target_type' => 'delivery', 'target_id' => $deliveryId],
                [
                    'stars' => $validated['stars'],
                    'delivery_task_id' => $taskId
                ]
            );

            // Update delivery average
            $avgRating = Rating::where('target_type', 'delivery')->where('target_id', $deliveryId)->avg('stars');
            $delivery->update(['rating_avg' => round($avgRating, 1)]);

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
    
    public function ratePharmacy(Request $request, int $orderId, int $pharmacyId)
    {
        $validated = $request->validate([
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

            $patientModel = Auth::user()->patient;
            if (!$patientModel) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Patient profile not found.'
                ], 404);
            }

            // Validate order exists, belongs to patient and pharmacist, and is delivered
            $order = Order::where('id', $orderId)
                ->where('pharmacist_id', $pharmacyId)
                ->where('patient_id', $patientModel->id)
                ->where('status', 'delivered')
                ->first();

            if (!$order) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'You can only rate pharmacists for orders that were completed for you.'
                ], 403);
            }

            // Validate pharmacist exists
            $pharmacist = Pharmacist::find($pharmacyId);
            if (!$pharmacist) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Pharmacist not found.'
                ], 404);
            }

            // Create or update rating
            $rating = Rating::updateOrCreate(
                ['user_id' => $userId, 'target_type' => 'pharmacist', 'target_id' => $pharmacyId],
                [
                    'stars' => $validated['stars'],
                    'order_id' => $orderId
                ]
            );

            // Update pharmacist average 
            $avgRating = Rating::where('target_type', 'pharmacist')->where('target_id', $pharmacyId)->avg('stars');
            $pharmacist->update(['rating_avg' => round($avgRating, 1)]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'rating_id' => $rating->id
                ],
                'message' => 'Rating submitted successfully'
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
    public function rateCareProvider(Request $request, int $sessionId, int $careProviderId)
    {
        $validated = $request->validate([
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

            $patientModel = Auth::user()->patient;
            if (!$patientModel) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Patient profile not found.'
                ], 404);
            }

            // Validate care provider exists
            $careProvider = CareProvider::find($careProviderId);
            if (!$careProvider) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Care provider not found.'
                ], 404);
            }

            // Validate home visit (session) exists, belongs to patient and care provider, and is completed
            $homeVisit = HomeVisit::where('id', $sessionId)
                ->where('care_provider_id', $careProviderId)
                ->where('patient_id', $patientModel->id)
                ->where('status', 'completed')
                ->first();

            if (!$homeVisit) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'You can only rate care providers after completing a session with them.'
                ], 403);
            }

            // Create or update rating
            $rating = Rating::updateOrCreate(
                ['user_id' => $userId, 'target_type' => 'care_provider', 'target_id' => $careProviderId],
                [
                    'stars' => $validated['stars'],
                    'home_visit_id' => $sessionId
                ]
            );

            // Update care provider average
            $avgRating = Rating::where('target_type', 'care_provider')->where('target_id', $careProviderId)->avg('stars');
            $careProvider->update(['rating_avg' => round($avgRating, 1)]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'rating_id' => $rating->id
                ],
                'message' => 'Rating submitted successfully'
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
