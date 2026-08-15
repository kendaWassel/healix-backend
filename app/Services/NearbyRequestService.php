<?php

namespace App\Services;

use App\Models\CareProvider;
use App\Models\HomeVisit;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class NearbyRequestService
{
    /**
     * Get pending home visit requests sorted by distance from provider location.
     * Nearby requests come first, farther requests come later.
     *
     * When $careProviderId is given, requests whose scheduled_at would
     * conflict with that provider's own already-booked visits (see
     * hasSchedulingConflict()) are excluded entirely — a provider should
     * never be offered a slot they can't actually take.
     */
    public function getNearbyPendingRequests(
        string $providerType,
        float $latitude,
        float $longitude,
        int $perPage = 10,
        ?float $maxDistanceKm = 15,
        ?int $careProviderId = null,
    ): LengthAwarePaginator {
        $distanceFormula = $this->buildHaversineFormula($latitude, $longitude);

        $query = HomeVisit::query()
            ->select('home_visits.*')
            ->join('patients', 'home_visits.patient_id', '=', 'patients.id')
            ->where('home_visits.service_type', $providerType)
            ->where('home_visits.status', 'pending')
            ->whereNull('home_visits.care_provider_id')
            ->whereNotNull('patients.latitude')
            ->whereNotNull('patients.longitude')
            ->selectRaw("{$distanceFormula} as distance_km")
            ->with([
                'patient.user',
                'doctor.user',
            ]);

        if ($careProviderId !== null) {
            $bufferMinutes = (int) config('home_visit.conflict_buffer_minutes', 60);

            $query->whereNotExists(function ($sub) use ($careProviderId, $bufferMinutes) {
                $sub->selectRaw('1')
                    ->from('home_visits as existing_visits')
                    ->where('existing_visits.care_provider_id', $careProviderId)
                    ->whereIn('existing_visits.status', ['accepted', 'in_progress'])
                    ->whereRaw(
                        'ABS(TIMESTAMPDIFF(MINUTE, existing_visits.scheduled_at, home_visits.scheduled_at)) <= ?',
                        [$bufferMinutes]
                    );
            });
        }

        // خلي القريب أول والبعيد آخر
        if (!is_null($maxDistanceKm)) {
            $query->orderByRaw("
                CASE
                    WHEN {$distanceFormula} <= ? THEN 0
                    ELSE 1
                END ASC
            ", [$maxDistanceKm]);
        }

        // ضمن كل مجموعة، الأقرب أول
        $query->orderBy('distance_km', 'asc');

        return $query->paginate($perPage);
    }

    /**
     * Whether accepting a visit at $scheduledAt would conflict with a visit
     * this provider already has accepted/in progress. Used as a hard check
     * at acceptance time — getNearbyPendingRequests() already filters these
     * out of the listing, but a request could still have gone stale between
     * when the provider fetched the list and when they tap accept.
     */
    public function hasSchedulingConflict(CareProvider $careProvider, Carbon $scheduledAt): bool
    {
        $bufferMinutes = (int) config('home_visit.conflict_buffer_minutes', 60);

        return HomeVisit::where('care_provider_id', $careProvider->id)
            ->whereIn('status', ['accepted', 'in_progress'])
            ->whereBetween('scheduled_at', [
                $scheduledAt->copy()->subMinutes($bufferMinutes),
                $scheduledAt->copy()->addMinutes($bufferMinutes),
            ])
            ->exists();
    }

    /**
     * Haversine formula in KM.
     */
    protected function buildHaversineFormula(float $latitude, float $longitude): string
    {
        $lat = (string) $latitude;
        $lng = (string) $longitude;

        return "(
            6371 * acos(
                cos(radians({$lat}))
                * cos(radians(patients.latitude))
                * cos(radians(patients.longitude) - radians({$lng}))
                + sin(radians({$lat}))
                * sin(radians(patients.latitude))
            )
        )";
    }
}
