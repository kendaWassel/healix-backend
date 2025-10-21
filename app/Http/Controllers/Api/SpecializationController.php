<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Specialization;
use Illuminate\Http\JsonResponse;

class SpecializationController extends Controller
{

    public function index(): JsonResponse
    {
        try {
            $specializations = Specialization::select('id', 'name')
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $specializations,
                'message' => 'Specializations retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve specializations',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

