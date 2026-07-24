<?php

namespace App\Http\Controllers\Api;

use App\Models\Specialization;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;


class SpecializationController extends Controller
{
    public function listForRegistration()
    {
        try {
            // name_ar must be selected too, otherwise the model has no
            // translation column loaded and always falls back to English.
            $specializations = Specialization::select('id', 'name', 'name_ar')
                ->orderBy(app()->getLocale() === 'ar' ? 'name_ar' : 'name', 'asc')
                ->get()
                ->map(fn (Specialization $specialization) => [
                    'id' => $specialization->id,
                    'name' => $specialization->name,
                ]);
            
            return response()->json([
                'status' => 'success',
                'data' => $specializations
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('medical.specializations_failed'),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function listForConsultation(Request $request)
    {
        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        // Get specializations without doctor count (all specializations)
        $query = Specialization::query();

        $perPage = $request->get('per_page');
        $specializationsPaginated = $query->paginate($perPage)
            ->appends($request->query());

        if ($specializationsPaginated->isEmpty()) {
            return response()->json([
                'status' => 'empty',
                'message' => __('medical.specializations_not_found'),
                'data' => [],
            ], 200);
        }

        $data = $specializationsPaginated->getCollection()->map(function ($specialization) {
            return [
                'id' => $specialization->id,
                'name' => $specialization->name,
                'doctors_count' => $specialization->doctors()->count(),
            ];
        })->values();

        $pager = [
            'current_page' => $specializationsPaginated->currentPage(),
            'last_page' => $specializationsPaginated->lastPage(),
            'per_page' => $specializationsPaginated->perPage(),
            'total' => $specializationsPaginated->total()
        ];

        return response()->json([
            'status' => 'success',
            'message' => __('medical.specializations_retrieved'),
            'data' => $data,
            'meta' => $pager,
        ], 200);
    }


}