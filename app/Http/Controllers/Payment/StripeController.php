<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\HomeVisit;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Stripe\Stripe;

class StripeController extends Controller
{
    protected array $payableMap = [
        'consultation' => Consultation::class,
        'order' => Order::class,
        'home_visit' => HomeVisit::class,
    ];

    /**
     * Stripe intent statuses that mean "this payment is still in flight".
     * A payable with one of these already has a usable client_secret, so a
     * second tap of "Pay" must reuse it rather than open a parallel charge.
     */
    protected const OPEN_STATUSES = [
        'requires_payment_method',
        'requires_confirmation',
        'requires_action',
        'processing',
    ];

    public function createIntent(Request $request): JsonResponse
    {
        // amount and currency are deliberately NOT accepted from the client:
        // both are derived from the payable on the server. A client-supplied
        // amount let any caller pay a 50-cent invoice for a $50 consultation.
        $request->validate([
            'payable_type' => ['required', 'string', Rule::in(array_keys($this->payableMap))],
            'payable_id' => 'required|integer|min:1',
            'payment_method_types' => 'sometimes|array',
            'payment_method_types.*' => 'string',
        ]);

        $payableType = $request->input('payable_type');
        $payableId = $request->input('payable_id');
        $payableClass = $this->payableMap[$payableType];

        $payable = $payableClass::find($payableId);

        if (! $payable) {
            return response()->json(['message' => __('payment.payable_not_found')], 404);
        }

        $user = $request->user();

        // Only the patient the payable belongs to may pay for it.
        if (! $this->userOwnsPayable($payable, $user)) {
            return response()->json(['message' => __('payment.not_authorized')], 403);
        }

        if (($payable->payment_status ?? null) === 'paid') {
            return response()->json(['message' => __('payment.already_paid')], 409);
        }

        $amount = $this->resolveAmount($payableType, $payable);

        // Null means the payable has no price yet — an unaccepted home visit has
        // no care provider, an order has no total until the pharmacy prices it.
        if ($amount === null) {
            return response()->json(['message' => __('payment.amount_unavailable')], 422);
        }

        // Stripe rejects charges below ~$0.50 in most currencies.
        if ($amount < 50) {
            return response()->json(['message' => __('payment.amount_too_small')], 422);
        }

        $currency = strtolower((string) config('services.stripe.currency', 'usd'));

        Stripe::setApiKey(config('services.stripe.secret'));

        // Reuse an in-flight intent for this payable instead of stacking up
        // parallel charges when the user taps Pay twice.
        if ($existing = $this->openPaymentFor($payableClass, $payableId)) {
            try {
                $intent = \Stripe\PaymentIntent::retrieve($existing->payment_intent_id);

                if (in_array($intent->status, self::OPEN_STATUSES, true)
                    && (int) $intent->amount === $amount) {
                    return response()->json([
                        'message' => __('payment.intent_created'),
                        'data' => [
                            'id' => $existing->id,
                            'payment_intent_id' => $intent->id,
                            'client_secret' => $intent->client_secret,
                            'status' => $intent->status,
                            'amount' => $intent->amount,
                            'currency' => strtoupper($intent->currency),
                        ],
                    ], 200);
                }
            } catch (\Throwable $e) {
                // Unreachable or stale intent — fall through and open a new one.
                Log::warning('Could not reuse existing payment intent', [
                    'payment_intent_id' => $existing->payment_intent_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            $intent = \Stripe\PaymentIntent::create([
                'amount' => $amount,
                'currency' => $currency,
                'payment_method_types' => $request->input('payment_method_types', ['card']),
                'metadata' => [
                    'user_id' => $user->id,
                    'payable_type' => $payableType,
                    'payable_id' => $payableId,
                ],
                'description' => sprintf('Payment for %s #%s', $payableType, $payableId),
            ]);
        } catch (\Throwable $e) {
            Log::error('Stripe payment intent creation failed', [
                'user_id' => $user->id,
                'payable_type' => $payableType,
                'payable_id' => $payableId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => __('payment.failed')], 502);
        }

        $payment = Payment::create([
            'user_id' => $user->id,
            'payable_id' => $payableId,
            'payable_type' => $payableClass,
            'payment_intent_id' => $intent->id,
            'amount' => $intent->amount,
            'currency' => strtoupper($intent->currency),
            'status' => $intent->status,
            'payment_method' => $intent->payment_method_types[0] ?? 'card',
            'metadata' => $intent->metadata->toArray() ?? [],
        ]);

        return response()->json([
            'message' => __('payment.intent_created'),
            'data' => [
                'id' => $payment->id,
                'payment_intent_id' => $intent->id,
                'client_secret' => $intent->client_secret,
                'status' => $intent->status,
                'amount' => $intent->amount,
                'currency' => strtoupper($intent->currency),
            ],
        ], 201);
    }

    public function webhook(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $signature, $webhookSecret);

            Log::info('Stripe webhook received', [
                'type' => $event->type,
                'id' => $event->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook signature verification failed: ' . $e->getMessage());

            return response()->json(['message' => __('payment.webhook_error')], 400);
        }

        $intent = $event->data->object;

        switch ($event->type) {
            case 'payment_intent.succeeded':
                // `charges` was removed from the PaymentIntent object in recent
                // API versions; the charge id now arrives as `latest_charge`.
                $this->markPayment($intent->id, 'paid', $intent->latest_charge ?? null);
                break;

            case 'payment_intent.payment_failed':
                $this->markPayment($intent->id, 'failed');
                break;

            case 'payment_intent.canceled':
                $this->markPayment($intent->id, 'cancelled');
                break;
        }

        return response()->json(['received' => true]);
    }

    public function status(string $paymentIntentId): JsonResponse
    {
        $payment = Payment::where('payment_intent_id', $paymentIntentId)->first();

        if (! $payment) {
            // An id we never issued must not become a lookup tool against the
            // whole Stripe account, so this is simply "not found".
            return response()->json(['message' => __('payment.intent_not_found')], 404);
        }

        // A payment record is readable only by the user who created it.
        if ((int) $payment->user_id !== (int) request()->user()?->id) {
            return response()->json(['message' => __('payment.not_authorized_view')], 403);
        }

        return response()->json([
            'message' => __('payment.status_retrieved'),
            'data' => [
                'payment_intent_id' => $payment->payment_intent_id,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'transaction_id' => $payment->transaction_id,
                'payable_type' => $payment->payable_type,
                'payable_id' => $payment->payable_id,
            ],
        ]);
    }

    /* ---------------------------------------------------------------
     | Internals
     |--------------------------------------------------------------- */

    /**
     * Apply a terminal status to a payment and mirror it onto the payable.
     */
    protected function markPayment(string $intentId, string $status, ?string $transactionId = null): void
    {
        $payment = Payment::where('payment_intent_id', $intentId)->first();

        if (! $payment) {
            Log::warning('Stripe webhook referenced an unknown payment intent', [
                'payment_intent_id' => $intentId,
            ]);

            return;
        }

        $attributes = ['status' => $status];

        if ($transactionId !== null) {
            $attributes['transaction_id'] = $transactionId;
        }

        $payment->update($attributes);

        if ($payment->payable) {
            $payment->payable->update(['payment_status' => $status]);
        }

        Log::info('Payment status updated from webhook', [
            'payment_id' => $payment->id,
            'status' => $status,
        ]);
    }

    /**
     * The authoritative price of a payable, in Stripe's minor units (cents).
     *
     * Returns null when the payable has no price yet, so the caller can answer
     * "not payable yet" rather than charging a wrong amount.
     */
    protected function resolveAmount(string $payableType, $payable): ?int
    {
        $major = match ($payableType) {
            'consultation' => $payable->doctor?->consultation_fee,
            'home_visit' => $payable->careProvider?->session_fee,
            // total_amount already includes the delivery fee once the driver
            // has set it (see DeliveryController::updateTaskStatus).
            'order' => $payable->total_amount,
            default => null,
        };

        if ($major === null || $major === '') {
            return null;
        }

        return (int) round(((float) $major) * 100);
    }

    /**
     * Whether this user is the patient the payable belongs to.
     */
    protected function userOwnsPayable($payable, $user): bool
    {
        $ownerUserId = $payable->patient?->user_id;

        return $ownerUserId !== null && (int) $ownerUserId === (int) $user->id;
    }

    /**
     * An existing, still-in-flight payment for the same payable, if any.
     */
    protected function openPaymentFor(string $payableClass, int $payableId): ?Payment
    {
        return Payment::where('payable_type', $payableClass)
            ->where('payable_id', $payableId)
            ->whereIn('status', self::OPEN_STATUSES)
            ->latest('id')
            ->first();
    }
}
