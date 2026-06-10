<?php

namespace App\Http\Controllers\payment;

use App\Http\Controllers\Controller;
use Stripe\Stripe;
use Illuminate\Http\Request;
class StripeController extends Controller
{
    public function createPaymentIntent(Request $request)
    {
        $request->validate([
            'amount' => 'required|integer|min:1', // Amount in cents
        ]);

        // Logic to create a payment intent using Stripe API
        Stripe::setApiKey(config('services.stripe.secret'));

        $amount = $request->input('amount'); // Amount in cents

        $intent = \Stripe\PaymentIntent::create([
            'amount' => $amount, // Amount in cents
            'currency' => 'SYP', // Currency code SY
            'payment_method_types' => ['card'], // Payment method types
        ]);

        return response()->json(['clientSecret' => $intent->client_secret]);
    }

    public function completePayment(Request $request)
    {
        // Logic to complete the payment after receiving confirmation from Stripe

        // This can include verifying the payment status and updating order records
            return response()->json(['message' => 'Payment completed successfully']);
    }
}
