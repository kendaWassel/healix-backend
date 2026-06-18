<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\HomeVisit;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Stripe\Stripe;

class StripeController extends Controller
{
    protected array $payableMap = [
        'consultation' => Consultation::class,
        'order' => Order::class,
        'home_visit' => HomeVisit::class,
    ];

    public function createIntent(Request $request): JsonResponse
    {
        $request->validate([
            'payable_type' => ['required', 'string', Rule::in(array_keys($this->payableMap))],
            'payable_id' => 'required|integer|min:1',
            'amount' => 'required|integer|min:50',
            'currency' => 'required|string|size:3',
            'payment_method_types' => 'sometimes|array',
            'payment_method_types.*' => 'string',
        ]);

        $payableType = $request->input('payable_type');
        $payableId = $request->input('payable_id');
        $payableClass = $this->payableMap[$payableType];

        $payable = $payableClass::find($payableId);

        if (! $payable) {
            return response()->json([ 'message' => 'Payable resource not found.' ], 404);
        }

        Stripe::setApiKey(config('services.stripe.secret'));

        $intent = \Stripe\PaymentIntent::create([
            'amount' => $request->input('amount'),
            'currency' => strtolower($request->input('currency')),
            'payment_method_types' => $request->input('payment_method_types', ['card']),
            'metadata' => [
                'user_id' => auth()->id(),
                'payable_type' => $payableType,
                'payable_id' => $payableId,
            ],
            'description' => sprintf('Payment for %s #%s', $payableType, $payableId),
        ]);

        $payment = Payment::create([
            'user_id' => auth()->id(),
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
            'message' => 'Payment intent created successfully.',
            'data' => [
                'id' => $payment->id,
                'payment_intent_id' => $intent->id,
                'client_secret' => $intent->client_secret,
                'status' => $intent->status,
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
        
        \Log::info('Webhook Received', [
            'type' => $event->type,
            'id' => $event->id,
            'data' => $event->data->object
        ]);

    } catch (\Exception $e) {
        \Log::error('Webhook Error: ' . $e->getMessage());
        return response()->json(['message' => 'Webhook error'], 400);
    }

    // التعامل مع حدث الدفع فقط
    if ($event->type === 'payment_intent.succeeded') {
        $intent = $event->data->object;
        
        $payment = Payment::where('payment_intent_id', $intent->id)->first();

        if ($payment) {
            $payment->update([
                'status' => 'paid',
                'transaction_id' => $intent->charges->data[0]->id ?? null,
            ]);

            if ($payment->payable) {
                $payment->payable->update(['payment_status' => 'paid']);
            }

            \Log::info('Payment Updated Successfully', ['payment_id' => $payment->id]);
        }
    }

    return response()->json(['received' => true]);
}

    public function status(string $paymentIntentId): JsonResponse
    {
        $payment = Payment::where('payment_intent_id', $paymentIntentId)->first();

        if ($payment) {
            return response()->json([
                'message' => 'Payment status retrieved.',
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

        Stripe::setApiKey(config('services.stripe.secret'));

        try {
            $intent = \Stripe\PaymentIntent::retrieve($paymentIntentId);
        } catch (\Exception $exception) {
            return response()->json(['message' => 'Payment intent not found.'], 404);
        }

        return response()->json([
            'message' => 'Payment status retrieved from Stripe.',
            'data' => [
                'payment_intent_id' => $intent->id,
                'status' => $intent->status,
                'amount' => $intent->amount,
                'currency' => strtoupper($intent->currency),
            ],
        ]);
    }
}
