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
            $specializations = Specialization::select('id', 'name')
                ->orderBy('name', 'asc')
                ->get();
            
            return response()->json([
                'status' => 'success',
                'data' => $specializations
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve specializations',
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

        // Pagination
        $perPage = $request->get('per_page');
        $specializationsPaginated = $query->paginate($perPage)
            ->appends($request->query());

        if ($specializationsPaginated->isEmpty()) {
            return response()->json([
                'status' => 'empty',
                'message' => 'No specializations found',
                'data' => [],
            ], 200);
        }

        $data = $specializationsPaginated->getCollection()->map(function ($specialization) {
            return [
                'id' => $specialization->id,
                'name' => $specialization->name,
                'doctors_count' => $specialization->doctors_count,
                // 'has_doctors' => $specialization->doctors_count > 0
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
            'message' => 'Specializations retrieved successfully.',
            'data' => $data,
            'meta' => $pager,
        ], 200);
    }


}

