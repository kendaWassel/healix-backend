<?php

namespace App\Http\Controllers\Api;

use App\Models\Specialization;
use App\Http\Controllers\Controller;


class SpecializationController extends Controller
{

    public function index()
    {
        try {
            $specializations = Specialization::select('id', 'name')
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $specializations,
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

