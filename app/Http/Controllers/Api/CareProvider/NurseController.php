<?php

namespace App\Http\Controllers\Api\CareProvider;

use App\Services\NurseService;
use App\Services\NearbyRequestService;
use App\Models\Upload;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class NurseController extends Controller
{
    protected $nurseService;
    protected $nearbyRequestService;

    public function __construct(
        NurseService $nurseService,
        NearbyRequestService $nearbyRequestService
    ) {
        $this->nurseService = $nurseService;
        $this->nearbyRequestService = $nearbyRequestService;
    }

    /**
     * Get nearby pending nurse requests
     * Frontend must send latitude + longitude
     */
    public function nearbyRequests(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $user = Auth::user();
        $careProvider = $user->careProvider;

        if (!$careProvider || $careProvider->type !== 'nurse') {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized or not a nurse.'
            ], 403);
        }

        try {
            $requests = $this->nearbyRequestService->getNearbyPendingRequests(
                providerType: 'nurse',
                latitude: (float) $request->latitude,
                longitude: (float) $request->longitude,
                perPage: (int) $request->get('per_page', 10),
                careProviderId: $careProvider->id,
            );

            $data = $requests->getCollection()->map(function ($visit) {
                return $this->nurseService->formatNearbyRequestData($visit);
            })->values();

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'meta' => [
                    'current_page' => $requests->currentPage(),
                    'last_page' => $requests->lastPage(),
                    'per_page' => $requests->perPage(),
                    'total' => $requests->total(),
                ]
            ]);
        } catch (\Exception $e) {
            $statusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    public function schedules(Request $request)
    {
        $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'status' => 'sometimes|in:accepted,in_progress,completed,cancelled',
        ]);

        $user = Auth::user();
        $careProvider = $user->careProvider;

        if (!$careProvider || $careProvider->type !== 'nurse') {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized or not a nurse.'
            ], 403);
        }

        $filters = [];
        if ($request->has('status')) {
            $filters['status'] = $request->status;
        }

        $visits = $this->nurseService->getSchedules($filters, $request->get('per_page', 10));

        $data = $visits->getCollection()->map(function ($visit) {
            return $this->nurseService->formatScheduleData($visit);
        })->values();

        $meta = [
            'current_page' => $visits->currentPage(),
            'last_page' => $visits->lastPage(),
            'per_page' => $visits->perPage(),
            'total' => $visits->total(),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => $meta,
        ]);
    }

    /**
     * Same nearby, conflict-filtered pending requests as nearbyRequests(),
     * but using the provider's saved profile location instead of query
     * params — a "my current requests" screen that doesn't need the app to
     * resend coordinates on every load.
     */
    public function orders(Request $request)
    {
        $user = Auth::user();
        $careProvider = $user->careProvider;

        if (!$careProvider || $careProvider->type !== 'nurse') {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized or not a nurse.'
            ], 403);
        }

        try {
            $visits = $this->nurseService->getOrders($request->get('per_page', 10));

            $data = $visits->getCollection()->map(function ($visit) {
                return $this->nurseService->formatNearbyRequestData($visit);
            })->values();

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'meta' => [
                    'current_page' => $visits->currentPage(),
                    'last_page' => $visits->lastPage(),
                    'per_page' => $visits->perPage(),
                    'total' => $visits->total(),
                ]
            ]);
        } catch (\Exception $e) {
            $statusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }

    }
        // return $this->nearbyRequests($request);
    

    public function accept($id)
    {
        try {
            $visit = $this->nurseService->acceptOrder($id);

            return response()->json([
                'status' => 'success',
                'message' => __('homevisit.accepted'),
                'data' => [
                    'id' => $visit->id,
                    'patient_id' => $visit->patient_id,
                    'status' => $visit->status,
                    'scheduled_at' => optional($visit->scheduled_at)?->toIso8601String(),
                ],
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    public function startSession($id)
    {
        $user = Auth::user();
        $careProvider = $user->careProvider;

        if (!$careProvider || $careProvider->type !== 'nurse') {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized or not a nurse.'
            ], 403);
        }

        try {
            $visit = $this->nurseService->startSession($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Session started successfully',
                'data' => [
                    'id' => $visit->id,
                    'started_at' => $visit->started_at->toIso8601String(),
                    'status' => $visit->status,
                ],
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    public function endSession($id)
    {
        $user = Auth::user();
        $careProvider = $user->careProvider;

        if (!$careProvider || $careProvider->type !== 'nurse') {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized or not a nurse.'
            ], 403);
        }

        try {
            $visit = $this->nurseService->endSession($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Session ended successfully',
                'data' => [
                    'id' => $visit->id,
                    'ended_at' => $visit->ended_at->toIso8601String(),
                    'status' => $visit->status,
                ],
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Get nurse profile
     */
    public function getProfile(Request $request)
    {
        $user = $request->user();
        $careProvider = $user->careProvider;

        if (!$careProvider || $careProvider->type !== 'nurse') {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.nurse_profile_not_found')
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('messages.profile_retrieved'),
            'data' => [
                'id' => $careProvider->id,
                'type' => $careProvider->type,
                'type_label' => \App\Support\Locale::label('service_type', $careProvider->type),
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'gender' => $careProvider->gender,
                'gender_label' => \App\Support\Locale::label('gender', $careProvider->gender),
                'session_fee' => $careProvider->session_fee,
                'latitude' => $careProvider->latitude,
                'longitude' => $careProvider->longitude,
                'available' => (bool) $careProvider->available,
                'is_online' => $careProvider->isOnline(),
                'location_updated_at' => optional($careProvider->last_location_updated_at)->toIso8601String(),
                'license_file_id' => $careProvider->license_file_id,
                'image_id' => $careProvider->care_provider_image_id,
                'license_file' => $careProvider->license_file_id ? asset('storage/' . ltrim($careProvider->licenseFile->file_path, '/')) : null,
                'image' => $careProvider->care_provider_image_id ? asset('storage/' . ltrim($careProvider->careProviderImage->file_path, '/')) : null,
                'rating_avg' => $careProvider->rating_avg,
                'role' => $user->role,
                'email_verified' => $user->email_verified_at ? true : false,
            ]
        ]);
    }

    /**
     * Update nurse profile
     */
    public function updateProfile(Request $request)
    {
        $request->validate([
        'full_name' => 'sometimes|string|max:255',
        'phone' => 'sometimes|string|max:20',
        'gender' => 'sometimes|in:male,female',
        'session_fee' => 'sometimes|numeric|min:0',
        'latitude' => 'sometimes|numeric',
        'longitude' => 'sometimes|numeric',
        // The client sends the actual file directly in this request.
        'image' => 'sometimes|file|mimes:jpeg,png,jpg,gif|max:2048',
        'license_file' => 'sometimes|file|mimes:pdf,jpeg,png,jpg,gif|max:5120',
        ]);

        $user = $request->user();
        $careProvider = $user->careProvider;

        if (!$careProvider || $careProvider->type !== 'nurse') {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.nurse_profile_not_found')
            ], 404);
        }

        // Core account fields live on the user row.
        if ($request->has('full_name')) {
            $user->full_name = $request->full_name;
        }
        if ($request->has('phone')) {
            $user->phone = $request->phone;
        }
        $user->save();

        if ($request->has('gender')) {
            $careProvider->gender = $request->gender;
        }
        if ($request->has('session_fee')) {
            $careProvider->session_fee = $request->session_fee;
        }
        if ($request->has('latitude')) {
        $careProvider->latitude = $request->latitude;
        }

        if ($request->has('longitude')) {
        $careProvider->longitude = $request->longitude;
        }

        if ($request->has('latitude') || $request->has('longitude')) {
            $careProvider->last_location_updated_at = now();
        }

        $oldImageId = $careProvider->care_provider_image_id;
        $oldLicenseId = $careProvider->license_file_id;

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
            $careProvider->care_provider_image_id = $upload->id;
        }
        if ($request->hasFile('license_file')) {
            $file = $request->file('license_file');
            $path = $file->store('certificates', 'public');
            $upload = Upload::create([
                'user_id' => $user->id,
                'category' => 'certificate',
                'file' => basename($path),
                'file_path' => $path,
                'mime' => $file->getClientMimeType(),
            ]);
            $careProvider->license_file_id = $upload->id;
        }

        $careProvider->save();

        if ($oldImageId && $oldImageId != $careProvider->care_provider_image_id) {
            if ($old = Upload::find($oldImageId)) {
                Storage::disk('public')->delete($old->file_path);
                $old->delete();
            }
        }
        if ($oldLicenseId && $oldLicenseId != $careProvider->license_file_id) {
            if ($old = Upload::find($oldLicenseId)) {
                Storage::disk('public')->delete($old->file_path);
                $old->delete();
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => __('messages.profile_updated'),
            'data' => [
                'care_provider' => [
                    'id' => $careProvider->id,
                    'full_name' => $user->full_name,
                    'phone' => $user->phone,
                    'gender' => $careProvider->gender,
                    'session_fee' => $careProvider->session_fee,
                    'image_id' => $careProvider->care_provider_image_id,
                    'image' => $careProvider->care_provider_image_id ? asset('storage/' . ltrim($careProvider->careProviderImage->file_path, '/')) : null,
                    'license_file_id' => $careProvider->license_file_id,
                    'license_file' => $careProvider->license_file_id ? asset('storage/' . ltrim($careProvider->licenseFile->file_path, '/')) : null,
                ]
            ]
        ]);
    }
}