<?php

namespace App\Http\Controllers;

use App\Models\FirstAid;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FirstAidController extends Controller
{
    /**
     * Get all First Aid articles
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        try {
            $perPage = $request->get('per_page', 10);
            
            $firstAids = FirstAid::orderBy('created_at', 'desc')
                ->paginate($perPage);

            $data = $firstAids->getCollection()->map(function ($firstAid) {
                return [
                    'id' => $firstAid->id,
                    'title' => $firstAid->title,
                    'description' => $firstAid->description,
                    'created_at' => $firstAid->created_at->format('Y-m-d'),
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'meta' => [
                    'current_page' => $firstAids->currentPage(),
                    'last_page' => $firstAids->lastPage(),
                    'total' => $firstAids->total(),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch first aid articles',
            ], 500);
        }
    }
}
