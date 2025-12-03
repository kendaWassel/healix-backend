<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Prescription;

class PrescriptionStatusController extends Controller
{
    public function show($prescription_id)
    {
        // احضر prescription وتأكد أنه موجود
        $prescription = Prescription::find($prescription_id);

        if (!$prescription) {
            return response()->json([
                'status' => 'error',
                'message' => 'Prescription not found'
            ], 404);
        }

        // احضر آخر order مرتبط بهذا prescription
        $order = Order::with('pharmacist.pharmacy')
            ->where('prescription_id', $prescription_id)
            ->latest()
            ->first();

        if (!$order) {
            return response()->json([
                'status' => 'error',
                'message' => 'No order found for this prescription'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'order_id' => $order->id,
                'status' => $order->status,
                'pharmacy' => [
                    'id' => $order->pharmacist->pharmacy->id ?? null,
                    'name' => $order->pharmacist->pharmacy->name ?? null,
                ],
            ],
            'message' => ''
        ], 200);
    }
}
