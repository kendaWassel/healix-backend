<?php

namespace App\Services;

use App\Models\HomeVisit;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class NurseService
{
    protected NearbyRequestService $nearbyRequestService;

    public function __construct(NearbyRequestService $nearbyRequestService)
    {
        $this->nearbyRequestService = $nearbyRequestService;
    }

    /**
     * ترجع طلبات الزيارات المنزلية القريبة للممرض
     */
    public function getNearbyRequests(int $perPage = 10): LengthAwarePaginator
    {
        $user = Auth::user();
        $careProvider = $user?->careProvider;

        if (!$careProvider || $careProvider->type !== 'nurse') {
            throw new \Exception('Unauthorized or not a nurse.', 403);
        }

        if (is_null($careProvider->latitude) || is_null($careProvider->longitude)) {
            throw new \Exception('Care provider location is not set.', 422);
        }

        return $this->nearbyRequestService->getNearbyPendingRequests(
            providerType: 'nurse',
            latitude: (float) $careProvider->latitude,
            longitude: (float) $careProvider->longitude,
            perPage: $perPage
        );
    }

    /**
     * orders = nearby requests
     */
    public function getOrders(int $perPage = 10): LengthAwarePaginator
    {
        return $this->getNearbyRequests($perPage);
    }

    public function formatNearbyRequestData(HomeVisit $visit): array
    {
        $patient = $visit->patient;

        return [
            'session_id'    => $visit->id,
            'patient_id'    => $visit->patient_id,
            'patient_name'  => $patient?->user?->full_name,
            'service'       => $visit->reason,
            'service_type'  => $visit->service_type,
            'address'       => $patient?->address,
            'scheduled_at'  => optional($visit->scheduled_at)?->toIso8601String(),
            'status'        => $visit->status,
            'distance_km'   => isset($visit->distance_km) ? round((float) $visit->distance_km, 2) : null,
        ];
    }

    public function formatOrderData(HomeVisit $visit): array
    {
        return $this->formatNearbyRequestData($visit);
    }

    public function getSchedules(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $user = Auth::user();
        $careProvider = $user?->careProvider;

        if (!$careProvider || $careProvider->type !== 'nurse') {
            throw new \Exception('Unauthorized or not a nurse.', 403);
        }

        $query = HomeVisit::with(['patient.user', 'careProvider.user'])
            ->where('service_type', 'nurse')
            ->where('care_provider_id', $careProvider->id);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        } else {
            $query->whereIn('status', ['accepted', 'in_progress', 'completed', 'cancelled']);
        }

        $visits = $query->orderBy('scheduled_at', 'asc')->paginate($perPage);

        // إذا الموعد مرّ ولسا ما بلشت الجلسة -> cancelled
        $now = now();
        $visits->getCollection()->each(function ($visit) use ($now) {
            if (
                $visit->status === 'accepted' &&
                $visit->scheduled_at &&
                $now->gt($visit->scheduled_at) &&
                is_null($visit->started_at)
            ) {
                $visit->status = 'cancelled';
                $visit->save();
            }
        });

        return $visits;
    }

    public function formatScheduleData(HomeVisit $visit): array
    {
        $patient = $visit->patient;

        return [
            'session_id'    => $visit->id,
            'patient_id'    => $visit->patient_id,
            'patient_name'  => $patient?->user?->full_name,
            'address'       => $patient?->address,
            'scheduled_at'  => optional($visit->scheduled_at)?->toIso8601String(),
            'status'        => $visit->status,
            'service'       => $visit->reason,
        ];
    }

    public function acceptOrder(int $id): HomeVisit
    {
        $user = Auth::user();
        $careProvider = $user?->careProvider;

        if (!$careProvider || $careProvider->type !== 'nurse') {
            throw new \Exception('Unauthorized or not a nurse.', 403);
        }

        $visit = HomeVisit::where('id', $id)
            ->where('status', 'pending')
            ->whereNull('care_provider_id')
            ->first();

        if (!$visit) {
            throw new \Exception('Visit not found or already accepted.', 404);
        }

        if ($visit->service_type !== 'nurse') {
            throw new \Exception('You can only accept nurse visits.', 403);
        }

        $visit->care_provider_id = $careProvider->id;
        $visit->status = 'accepted';
        $visit->save();

        return $visit;
    }

    public function startSession(int $id): HomeVisit
    {
        $user = Auth::user();
        $careProvider = $user?->careProvider;

        if (!$careProvider || $careProvider->type !== 'nurse') {
            throw new \Exception('Unauthorized or not a nurse.', 403);
        }

        $visit = HomeVisit::where('id', $id)
            ->where('care_provider_id', $careProvider->id)
            ->where('service_type', 'nurse')
            ->where('status', 'accepted')
            ->first();

        if (!$visit) {
            throw new \Exception('Visit not found or not in accepted status.', 404);
        }

        if ($visit->scheduled_at && now()->lt($visit->scheduled_at)) {
            throw new \Exception('Cannot start session before the scheduled time.', 400);
        }

        $visit->status = 'in_progress';
        $visit->started_at = now();
        $visit->save();

        return $visit;
    }

    public function endSession(int $id): HomeVisit
    {
        $user = Auth::user();
        $careProvider = $user?->careProvider;

        if (!$careProvider || $careProvider->type !== 'nurse') {
            throw new \Exception('Unauthorized or not a nurse.', 403);
        }

        $visit = HomeVisit::where('id', $id)
            ->where('care_provider_id', $careProvider->id)
            ->where('service_type', 'nurse')
            ->where('status', 'in_progress')
            ->whereNotNull('started_at')
            ->first();

        if (!$visit) {
            throw new \Exception('Visit not found or not in progress.', 404);
        }

        $visit->ended_at = now();
        $visit->status = 'completed';
        $visit->save();

        return $visit;
    }
}