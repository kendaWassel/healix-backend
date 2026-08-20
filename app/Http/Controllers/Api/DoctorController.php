<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePrescriptionRequest;
use App\Models\Doctor;
use App\Models\Rating;
use App\Models\Upload;
use App\Services\DoctorService;
use App\Support\Locale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DoctorController extends Controller
{
    protected $doctorService;

    public function __construct(DoctorService $doctorService)
    {
        $this->doctorService = $doctorService;
    }

    public function getDoctorsBySpecialization(Request $request)
    {
        $validated = $request->validate([
            'specialization_id' => 'required|integer|exists:specializations,id',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        try {
            $perPage = $request->get('per_page', 10);
            $result = $this->doctorService->getDoctorsBySpecialization($validated['specialization_id'], $perPage);

            if (empty($result['doctors'])) {
                return response()->json([
                    'status' => 'empty',
                    'message' => __('consultation.no_doctors_for_specialization'),
                    'specialization' => $result['specialization'],
                    'data' => [],
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'data' => $result['doctors'],
                'meta' => $result['meta'],
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }
    public function getAvailableSlots(Request $request, $doctorId)
    {
        $validated = $request->validate([
            'date' => 'sometimes|date',
        ]);

        try {
            $date = $request->query('date');
            $result = $this->doctorService->getAvailableSlots($doctorId, $date);

            return response()->json($result, 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json([
                'message' => $e->getMessage(),
            ], $statusCode);
        }
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

        try {
            $filters = [];
            if ($request->filled('status')) {
                $filters['status'] = $validated['status'];
            }
            if ($request->filled('date')) {
                $filters['date'] = $validated['date'];
            }

            $perPage = $request->get('per_page', 10);
            $latest = $request->boolean('latest');

            $result = $this->doctorService->getDoctorSchedules($filters, $perPage, $latest);

            return response()->json([
                'status' => 'success',
                'data' => $result['data'],
                'meta' => $result['meta'],
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    public function createPrescription(CreatePrescriptionRequest $request)
    {
        $validated = $request->validated();
        
        try {
            $prescription = $this->doctorService->createPrescription($validated);
            return response()->json([
                'status'  => 'success',
                'data'    => [
                    'prescription_id' => $prescription->id,
                    'status'          => $prescription->status,
                    'status_label'    => Locale::label('prescription_status', $prescription->status),
                    'issued_at'       => $prescription->created_at?->toIso8601String(),
                ],
                'message' => __('pharmacy.prescription_created'),
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
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
                'message' => __('rating.retrieve_failed'),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get doctor profile
     */
    public function getProfile(Request $request)
    {
        $user = Auth::user();
        $doctor = $user->doctor;

        if (!$doctor) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.doctor_profile_not_found')
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('messages.profile_retrieved'),
            'data' => [
                'id' => $doctor->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'gender' => $doctor->gender,
                // 'gender_label' => \App\Support\Locale::label('gender', $doctor->gender),
                'specialization' => $doctor->specialization ? $doctor->specialization->name : null,
                'specialization_id' => $doctor->specialization_id,
                'from' => $doctor->from,
                'to' => $doctor->to,
                'consultation_fee' => $doctor->consultation_fee,
                'rating_avg' => $doctor->rating_avg,
                'image' => $doctor->doctor_image_id ? asset('/storage/' . $doctor->doctorImage->file_path) : null,
                'image_id' => $doctor->doctor_image_id,
                'certificate_file' => $doctor->certificate_file_id ? asset('/storage/' . $doctor->certificateFile->file_path) : null,
                'certificate_file_id' => $doctor->certificate_file_id,
                // 'role' => $user->role,
                // 'email_verified' => $user->email_verified_at ? true : false,
            ]
        ]);
    }

    /**
     * Update doctor profile
     */
    public function updateProfile(Request $request)
    {
        $request->validate([
            'full_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'phone' => 'sometimes|string|max:20',
            'gender' => 'sometimes|in:male,female',
            'from' => 'sometimes',
            'to' => 'sometimes',
            'consultation_fee' => 'sometimes|numeric|min:0',
            // The client sends the actual file directly in this request.
            'image' => 'sometimes|file|mimes:jpeg,png,jpg,gif|max:2048',
            'certificate_file' => 'sometimes|file|mimes:pdf,jpeg,png,jpg,gif|max:5120',
        ]);

        $user = Auth::user();
        $doctor = $user->doctor;

        if (!$doctor) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.doctor_profile_not_found')
            ], 404);
        }

        // Core account fields live on the user row.
        if ($request->has('full_name')) {
            $user->full_name = $request->full_name;
        }
        if ($request->has('phone')) {
            $user->phone = $request->phone;
        }
        if ($request->has('email')) {
            $user->email = $request->email;
        }
        $user->save();

        if ($request->has('gender')) {
            $doctor->gender = $request->gender;
        }
        if ($request->has('from')) {
            $doctor->from = $request->from;
        }
        if ($request->has('to')) {
            $doctor->to = $request->to;
        }
        if ($request->has('consultation_fee')) {
            $doctor->consultation_fee = $request->consultation_fee;
        }

        // Remember the old ids so the files they point to can be deleted once
        // the new ones are safely saved.
        $oldImageId = $doctor->doctor_image_id;
        $oldCertificateId = $doctor->certificate_file_id;

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $path = $file->store('profile_images', 'public');
            $upload = Upload::create([
                'user_id' => $user->id,
                'category' => 'profile',
                'file' => basename($path),
                'file_path' => $path,
                'mime' => $file->getClientMimeType(),
            ]);
            $doctor->doctor_image_id = $upload->id;
        }
        if ($request->hasFile('certificate_file')) {
            $file = $request->file('certificate_file');
            $path = $file->store('certificates', 'public');
            $upload = Upload::create([
                'user_id' => $user->id,
                'category' => 'certificate',
                'file' => basename($path),
                'file_path' => $path,
                'mime' => $file->getClientMimeType(),
            ]);
            $doctor->certificate_file_id = $upload->id;
        }
        $doctor->save();

        // Old image was replaced by a different one -> delete the old file + row.
        if ($oldImageId && $oldImageId != $doctor->doctor_image_id) {
            if ($old = Upload::find($oldImageId)) {
                Storage::disk('public')->delete($old->file_path);
                $old->delete();
            }
        }
        if ($oldCertificateId && $oldCertificateId != $doctor->certificate_file_id) {
            if ($old = Upload::find($oldCertificateId)) {
                Storage::disk('public')->delete($old->file_path);
                $old->delete();
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => __('messages.profile_updated'),
            'data' => [
                'doctor' => [
                    'id' => $doctor->id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'from' => $doctor->from,
                    'to' => $doctor->to,
                    'consultation_fee' => $doctor->consultation_fee,
                    'gender' => $doctor->gender,
                    'image' => $doctor->doctor_image_id ? asset('/storage/' . $doctor->doctorImage->file_path) : null,
                    'image_id' => $doctor->doctor_image_id,
                    'certificate_file' => $doctor->certificate_file_id ? asset('/storage/' . $doctor->certificateFile->file_path) : null,
                    'certificate_file_id' => $doctor->certificate_file_id,
                ]
            ]
        ]);
    }
}
