<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\Specialization;
use Illuminate\Support\Facades\Auth;

class ConsultationController extends Controller
{
  public function store(Request $request)
    {
        $validated = $request->validate([
            'doctor_id' => 'required|exists:doctors,id',
            'type' => 'required|in:call_now,schedule',
            'scheduled_at' => 'nullable|date',
        ]);

        if ($validated['type'] === 'schedule' && !$validated['scheduled_at']) {
            return response()->json(['error' => 'scheduled_at is required for scheduled consultations'], 422);
        }

        $consultation = Consultation::create([
            'patient_id' => Auth::id(),
            'doctor_id' => $validated['doctor_id'],
            'type' => $validated['type'],
            'status' => 'pending',
            'scheduled_at' => $validated['scheduled_at'] ?? null,
        ]);

        return response()->json([
            'status' => 'created',
            'consultation' => $consultation,
        ], 200);
    }
}
