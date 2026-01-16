<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pharmacist;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PharmacyController extends Controller
{
    public function getPharmacies(Request $request)
    {
        $validated = $request->validate([
            'city'     => 'sometimes|string|max:255',
            'page'     => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $perPage = $request->get('per_page', 10);

        // Since we don't have an explicit city column, filter by address containing the city (best-effort).
        $query = Pharmacist::with('user')
            ->when($request->filled('city'), function ($q) use ($validated) {
                $city = $validated['city'];
                $q->where('address', 'like', '%' . $city . '%');
            });

        $pharmacies = $query->paginate($perPage)->appends($request->query());

        $now = Carbon::now('Asia/Damascus');

        $data = $pharmacies->getCollection()->map(function (Pharmacist $pharmacy) use ($now, $validated) {
            //parse from and to times
            $from = Carbon::createFromFormat('H:i:s', $pharmacy->from, 'Asia/Damascus');
            $to   = Carbon::createFromFormat('H:i:s', $pharmacy->to, 'Asia/Damascus');


            $openNow = false;
            if ($from && $to) {
                $openNow = $now->between($from, $to);
            }

            return [
                'id'        => $pharmacy->id,
                'name'      => $pharmacy->pharmacy_name,
                'address'   => $pharmacy->address,
                'city'      => $validated['city'] ?? null, // best-effort; real city not stored
                'latitude'  => $pharmacy->latitude ? (float) $pharmacy->latitude : null,
                'longitude' => $pharmacy->longitude ? (float) $pharmacy->longitude : null,
                'open_now'  => $openNow,
                'from'      => Carbon::parse($pharmacy->from)->format('H:i'),
                'to'        => Carbon::parse($pharmacy->to)->format('H:i'),
                'rating'    => $pharmacy->rating_avg ? (float) $pharmacy->rating_avg : 0.0,
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'data'   => $data,
            'meta'   => [
                'current_page' => $pharmacies->currentPage(),
                'last_page'    => $pharmacies->lastPage(),
                'total'        => $pharmacies->total(),
            ],
        ], 200);
    }


    public function getPharmacyDetails($id)
    {
        $pharmacy = Pharmacist::with('user')->find($id);

        if (!$pharmacy) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Pharmacy not found.',
            ], 404);
        }

        $user = $pharmacy->user;

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id'        => $pharmacy->id,
                'name'      => $pharmacy->pharmacy_name,
                'city'      => null, // not explicitly stored
                'area'      => null, // not explicitly stored
                'address'   => $pharmacy->address,
                'latitude'  => $pharmacy->latitude ? (float) $pharmacy->latitude : null,
                'longitude' => $pharmacy->longitude ? (float) $pharmacy->longitude : null,
                'phone'     => $user?->phone,
            ],
        ], 200);
    }
}


