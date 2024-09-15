<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Stripe\StripeClient;
class PaymentController extends Controller
{
    public function createPaymentIntent(Request $request)
    {
       // Initialize Stripe client with your secret key
       $stripe = new StripeClient(env('STRIPE_SECRET'));

       // Use an existing Customer ID if this is a returning customer.
       $customer = $stripe->customers->create();
       $ephemeralKey = $stripe->ephemeralKeys->create([
           'customer' => $customer->id,
       ], [
           'stripe_version' => '2024-04-10',
       ]);
       $paymentIntent = $stripe->paymentIntents->create([
        'amount' => $request->amount * 100,
        'currency' => 'aed',
        'customer' => $customer->id,
        'automatic_payment_methods' => [
          'enabled' => 'true',
        ],
      ]);

       // Return the response as JSON
       return response()->json([
           'paymentIntent' => $paymentIntent->client_secret,
           'ephemeralKey' => $ephemeralKey->secret,
           'customer' => $customer->id,
           'publishableKey' => env('STRIPE_PUBLISHABLE_KEY')
       ]);
    }
    public function checkPaymentStatus($paymentIntentId)
    {
        // Initialize Stripe client with your secret key
        $stripe = new StripeClient(env('STRIPE_SECRET'));

        try {
            // Retrieve the payment intent ID from the request or your database
            $paymentIntentId = $paymentIntentId;

            // Fetch the payment intent object
            $paymentIntent = $stripe->paymentIntents->retrieve($paymentIntentId);

            // Get the customer associated with this payment intent
            $customer = $stripe->customers->retrieve($paymentIntent->customer);

            $amount = $paymentIntent->amount / 100;

            return $paymentIntent->status;

        } catch (\Exception $e) {
            // Handle API error
            return 'error';
        }
    }
}
