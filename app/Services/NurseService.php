<?php

namespace App\Services;

use App\Models\HomeVisit;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class NurseService
{

    public function getSchedules(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $user = Auth::user();
        $careProvider = $user->careProvider;

        $query = HomeVisit::with(['patient.user', 'careProvider.user'])
            ->where('service_type', 'nurse')
            ->where('care_provider_id', $careProvider->id);

        // Apply status filter if provided
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        } else {
            // Default to accepted and in_progress if no status filter
            $query->whereIn('status', ['accepted', 'in_progress', 'completed', 'cancelled']);
        }

        $visits = $query->orderBy('scheduled_at', 'asc')
            ->paginate($perPage);

        // When Time of Session Arrived And Nurse Not Started Yet, Change Status to Cancelled
        $now = now();
        $visits->getCollection()->each(function ($visit) use ($now) {
            if ($visit->status === 'accepted' && $now->gt($visit->scheduled_at) && is_null($visit->started_at)) {
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
            'session_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'patient_name' => $patient?->user?->full_name,
            'address' => $patient?->address,
            'scheduled_at' => $visit->scheduled_at->toIso8601String(),
            'status' => $visit->status,
            'service' => $visit->reason,
        ];
    }

    public function getOrders(int $perPage = 10): LengthAwarePaginator
    {
        $orders = HomeVisit::with('patient.user')
            ->whereIn('status', ['pending'])
            ->where('service_type', 'nurse')
            ->orderBy('scheduled_at', 'asc')
            ->paginate($perPage);

        return $orders;
    }

    public function formatOrderData(HomeVisit $visit): array
    {
        $patient = $visit->patient;
        return [
            'session_id' => $visit->id,
            'patient_name' => $patient?->user?->full_name,
            'service' => $visit->reason,
            'service_type' => $visit->service_type,
            'address' => $patient?->address,
            'status' => $visit->status,
            'scheduled_at' => $visit->scheduled_at->toIso8601String(),
        ];
    }

    public function acceptOrder(int $id): HomeVisit
    {
        $user = Auth::user();
        $careProvider = $user->careProvider;

        $visit = HomeVisit::where('id', $id)
            ->where('status', 'pending')
            ->first();

        if (!$visit) {
            throw new \Exception('Visit not found or not pending', 404);
        }

        if ($visit->service_type !== 'nurse') {
            throw new \Exception('You can only accept nurse visits', 403);
        }

        if ($visit->status == 'accepted') {
            throw new \Exception('This session is already accepted from another nurse', 400);
        }

        $visit->care_provider_id = $careProvider->id;
        $visit->status = 'accepted';
        $visit->save();

        return $visit;
    }

    public function startSession(int $id): HomeVisit
    {
        $user = Auth::user();
        $careProvider = $user->careProvider;

        $visit = HomeVisit::where('id', $id)
            ->where('care_provider_id', $careProvider->id)
            ->where('service_type', 'nurse')
            ->where('status', 'accepted')
            ->first();

        if (!$visit) {
            throw new \Exception('Visit not found or not in accepted status.', 404);
        }

        // Check if the scheduled time has arrived   
        if (now()->lt($visit->scheduled_at)) {
            throw new \Exception('Cannot start session before the scheduled time.', 400);
        }
        // Check if the status is cancelled
        if ($visit->status === 'cancelled') {
            throw new \Exception('You can not start the session because the session is cancelled.', 400);
        }

        $visit->status = 'in_progress';
        $visit->started_at = now();
        $visit->save();

        return $visit;
    }

    public function endSession(int $id): HomeVisit
    {
        $user = Auth::user();
        $careProvider = $user->careProvider;

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

