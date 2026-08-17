<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Services\AssessmentBookingService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssessmentController extends Controller
{
    public function __construct(protected AssessmentBookingService $bookingService) {}

    /**
     * Unified endpoint for the post-assessment booking screen: the
     * assessment result, its resolved specialty, and available doctors with
     * their slots for the requested (or today's) date — one call instead of
     * three separate ones.
     */
    public function bookingOptions(Request $request, int $id)
    {
        $validated = $request->validate([
            'date' => 'sometimes|date',
        ]);

        try {
            $assessment = Assessment::with('conversation')->findOrFail($id);

            $patient = Auth::user()?->patient;
            if (! $patient || $assessment->conversation?->patient_id !== $patient->id) {
                throw new \Exception(__('messages.unauthorized'), 403);
            }

            $data = $this->bookingService->getBookingOptions($assessment, $validated['date'] ?? null);

            return response()->json([
                'status' => 'success',
                'data' => $data,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => __('ai.assessment_not_found'),
            ], 404);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }
}
