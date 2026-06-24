<?php

namespace App\Services;

use App\Models\HomeVisit;
use Illuminate\Pagination\LengthAwarePaginator;

class NearbyRequestService
{
    /**
     * Get pending home visit requests sorted by distance from provider location.
     * Nearby requests come first, farther requests come later.
     */
    public function getNearbyPendingRequests(
        string $providerType,
        float $latitude,
        float $longitude,
        int $perPage = 10,
        ?float $maxDistanceKm = 15
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