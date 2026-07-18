<?php

namespace App\Http\Controllers\Api\DDI;

use App\Exceptions\AI\AIServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DDI\AllergyCheckRequest;
use App\Http\Requests\DDI\BatchInteractionRequest;
use App\Http\Requests\DDI\CheckInteractionRequest;
use App\Http\Requests\DDI\PregnancyCheckRequest;
use App\Http\Requests\DDI\ResolveDrugRequest;
use App\Http\Requests\DDI\ScreenMedicationsRequest;
use App\Http\Resources\DrugInteractionCheckResource;
use App\Models\DrugInteractionCheck;
use App\Services\DDI\DdiService;
use App\Services\DDI\DrugInteractionCheckRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DrugInteractionController extends Controller
{
    public function __construct(
        protected DdiService $ddiService,
        protected DrugInteractionCheckRecorder $recorder,
    ) {}

    /**
     * Resolve a drug name to its RxCUI identifier (with a "did you mean?"
     * suggestion for misspellings). Useful for validating/autocompleting
     * drug names before running the heavier interaction checks.
     *
     * Not persisted — this is a lookup helper, not a clinical check.
     */
    public function resolve(ResolveDrugRequest $request): JsonResponse
    {
        return $this->handle('Drug name resolved.', fn () => $this->ddiService->resolveDrug(
            $request->validated('name'),
        ));
    }

    /**
     * Check a single drug pair for an interaction, its severity and
     * safer alternatives.
     */
    public function checkInteraction(CheckInteractionRequest $request): JsonResponse
    {
        return $this->handleAndRecord(
            $request,
            'Drug interaction check completed.',
            DrugInteractionCheck::TYPE_INTERACTION,
            $request->validated(),
            fn () => $this->ddiService->checkInteraction(
                $request->validated('drug_a'),
                $request->validated('drug_b'),
                $request->boolean('include_alternatives', true),
            ),
        );
    }

    /**
     * Check several explicit drug pairs in one request.
     */
    public function checkBatch(BatchInteractionRequest $request): JsonResponse
    {
        return $this->handleAndRecord(
            $request,
            'Batch drug interaction check completed.',
            DrugInteractionCheck::TYPE_BATCH,
            $request->validated(),
            fn () => $this->ddiService->checkInteractionBatch(
                $request->validated('pairs'),
            ),
        );
    }

    /**
     * Screen a full medication list for all pairwise interactions.
     */
    public function screenMedications(ScreenMedicationsRequest $request): JsonResponse
    {
        return $this->handleAndRecord(
            $request,
            'Medication list screened successfully.',
            DrugInteractionCheck::TYPE_SCREEN,
            $request->validated(),
            fn () => $this->ddiService->screenMedications(
                $request->validated('drugs'),
            ),
        );
    }

    /**
     * Find drugs that may cross-react with a known allergy.
     */
    public function checkAllergy(AllergyCheckRequest $request): JsonResponse
    {
        return $this->handleAndRecord(
            $request,
            'Allergy cross-reactivity check completed.',
            DrugInteractionCheck::TYPE_ALLERGY,
            $request->validated(),
            fn () => $this->ddiService->checkAllergyCrossReactivity(
                $request->validated('drug'),
                (int) $request->validated('max_results', 10),
            ),
        );
    }

    /**
     * Pregnancy safety for one drug or a combination of two.
     */
    public function checkPregnancy(PregnancyCheckRequest $request): JsonResponse
    {
        return $this->handleAndRecord(
            $request,
            'Pregnancy safety check completed.',
            DrugInteractionCheck::TYPE_PREGNANCY,
            $request->validated(),
            fn () => $this->ddiService->checkPregnancySafety(
                $request->validated('drug_a'),
                $request->validated('drug_b'),
                $request->boolean('live_api', true),
            ),
        );
    }

    /**
     * List the authenticated user's persisted drug-safety checks (newest first).
     */
    public function history(Request $request): JsonResponse
    {
        $checks = DrugInteractionCheck::where('user_id', $request->user()->id)
            ->when($request->filled('check_type'), fn ($q) => $q->where('check_type', $request->string('check_type')))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Drug interaction checks retrieved successfully.',
            'data' => DrugInteractionCheckResource::collection($checks->items()),
            'meta' => [
                'current_page' => $checks->currentPage(),
                'last_page' => $checks->lastPage(),
                'per_page' => $checks->perPage(),
                'total' => $checks->total(),
            ],
        ]);
    }

    /**
     * Show one persisted check owned by the authenticated user.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $check = DrugInteractionCheck::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $check) {
            return response()->json([
                'success' => false,
                'message' => 'Drug interaction check not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Drug interaction check retrieved successfully.',
            'data' => new DrugInteractionCheckResource($check),
        ]);
    }

    /**
     * Run a DDI service call, persist it to the history table, and return the
     * service result in the API's standard success/message/data envelope.
     *
     * @param  array<string, mixed>  $input  Validated request payload to store.
     */
    protected function handleAndRecord(Request $request, string $successMessage, string $checkType, array $input, callable $serviceCall): JsonResponse
    {
        return $this->handle($successMessage, function () use ($request, $checkType, $input, $serviceCall) {
            $result = $serviceCall();

            // Persistence must never break the clinical response the user asked for.
            try {
                $this->recorder->record($request->user(), $checkType, $input, $result);
            } catch (\Throwable $e) {
                Log::error('Failed to persist drug interaction check', [
                    'user_id' => $request->user()?->id,
                    'check_type' => $checkType,
                    'error' => $e->getMessage(),
                ]);
            }

            return $result;
        });
    }

    /**
     * Run a DDI service call and wrap the outcome in the API's standard
     * success/message/data envelope.
     */
    protected function handle(string $successMessage, callable $callback): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'data' => $callback(),
            ]);
        } catch (AIServiceException $e) {
            Log::error('DDI service error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $this->httpStatus($e->getCode()));
        } catch (\Throwable $e) {
            Log::error('DDI check unexpected error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred during the drug safety check.',
            ], 500);
        }
    }

    protected function httpStatus(int $code): int
    {
        return $code >= 400 && $code < 600 ? $code : 502;
    }
}
