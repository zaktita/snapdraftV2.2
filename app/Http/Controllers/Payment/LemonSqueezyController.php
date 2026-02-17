<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Mail\PaymentCancelled as PaymentCancelledMail;
use App\Mail\PaymentFailed as PaymentFailedMail;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class LemonSqueezyController extends Controller
{
    private const LEMON_SQUEEZY_API_URL = 'https://api.lemonsqueezy.com/v1/checkouts';

    /**
     * Create a Lemon Squeezy checkout and redirect the user to it.
     * When request expects JSON (e.g. AJAX), returns { redirect_url } or { message } for errors.
     */
    public function checkout(Request $request): JsonResponse|RedirectResponse
    {
        $wantsJson = $request->expectsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest';
        $genericError = 'Unable to start checkout. Please try again or contact support.';

        try {
            return $this->performCheckout($request, $wantsJson, $genericError);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Lemon Squeezy checkout unexpected error', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $wantsJson
                ? response()->json(['message' => $genericError], 500)
                : redirect()->route('client-area.checkout')->with('error', $genericError);
        }
    }

    /**
     * @return JsonResponse|RedirectResponse
     */
    private function performCheckout(Request $request, bool $wantsJson, string $genericError): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'uuid', 'exists:plans,id'],
            'billing_email' => ['nullable', 'email'],
            'billing_first_name' => ['nullable', 'string', 'max:255'],
            'billing_last_name' => ['nullable', 'string', 'max:255'],
            'billing_address' => ['nullable', 'string', 'max:500'],
            'billing_city' => ['nullable', 'string', 'max:255'],
            'billing_state' => ['nullable', 'string', 'max:255'],
            'billing_postal_code' => ['nullable', 'string', 'max:50'],
            'billing_country' => ['nullable', 'string', 'size:2'],
            'tax_id' => ['nullable', 'string', 'max:100'],
        ]);

        $plan = Plan::find($validated['plan_id']);

        if (! $plan || ! $plan->is_active) {
            $msg = 'Plan not found or is no longer available.';
            return $wantsJson
                ? response()->json(['message' => $msg], 422)
                : redirect()->route('client-area.checkout')->with('error', $msg);
        }

        $variantId = $plan->provider_product_id;

        if (empty($variantId)) {
            Log::warning('Lemon Squeezy checkout attempted for plan without provider_product_id', ['plan_id' => $plan->id]);
            $msg = 'This plan is not available for checkout. Set Provider Product ID (Lemon Squeezy variant ID) in the plan.';
            return $wantsJson
                ? response()->json(['message' => $msg], 422)
                : redirect()->route('client-area.checkout')->with('error', $msg);
        }

        $storeId = config('services.lemonsqueezy.store_id');
        $apiKey = config('services.lemonsqueezy.api_key');

        if (empty($storeId) || empty($apiKey)) {
            Log::error('Lemon Squeezy not configured: missing store_id or api_key');
            $msg = 'Payment is not configured. Please try again later.';
            return $wantsJson
                ? response()->json(['message' => $msg], 503)
                : redirect()->route('client-area.checkout')->with('error', $msg);
        }

        $user = $request->user();
        $redirectUrl = route('client-area.checkout.success');

        $price = (float) $plan->price;
        $customPrice = (int) round($price * 100);
        if ($customPrice <= 0) {
            $msg = 'This plan has an invalid price. Please contact support.';
            return $wantsJson
                ? response()->json(['message' => $msg], 422)
                : redirect()->route('client-area.checkout')->with('error', $msg);
        }

        $testMode = filter_var(config('services.lemonsqueezy.test_mode', false), FILTER_VALIDATE_BOOLEAN);

        $payload = [
            'data' => [
                'type' => 'checkouts',
                'attributes' => [
                    'custom_price' => $customPrice,
                    'product_options' => [
                        'redirect_url' => $redirectUrl,
                    ],
                    'checkout_data' => [
                        'email' => (string) ($validated['billing_email'] ?? $user?->email ?? ''),
                        'name' => (string) (trim(($validated['billing_first_name'] ?? '') . ' ' . ($validated['billing_last_name'] ?? '')) ?: $user?->name ?? ''),
                        'billing_address' => [
                            'country' => (string) ($validated['billing_country'] ?? $user?->billing_country ?? ''),
                            'zip' => (string) ($validated['billing_postal_code'] ?? $user?->billing_postal_code ?? ''),
                        ],
                        'custom' => [
                            'user_id' => (string) ($user?->id ?? ''),
                            'plan_id' => (string) $plan->id,
                        ],
                    ],
                    'test_mode' => $testMode,
                ],
                'relationships' => [
                    'store' => [
                        'data' => [
                            'type' => 'stores',
                            'id' => (string) $storeId,
                        ],
                    ],
                    'variant' => [
                        'data' => [
                            'type' => 'variants',
                            'id' => (string) $variantId,
                        ],
                    ],
                ],
            ],
        ];

        try {
            $response = Http::timeout(15)->withHeaders([
                'Accept' => 'application/vnd.api+json',
                'Content-Type' => 'application/vnd.api+json',
                'Authorization' => 'Bearer ' . $apiKey,
            ])->post(self::LEMON_SQUEEZY_API_URL, $payload);
        } catch (\Throwable $e) {
            Log::error('Lemon Squeezy checkout request failed', [
                'message' => $e->getMessage(),
                'plan_id' => $plan->id,
            ]);
            return $wantsJson
                ? response()->json(['message' => $genericError], 502)
                : redirect()->route('client-area.checkout')->with('error', $genericError);
        }

        $body = $response->json() ?? [];

        if (! $response->successful()) {
            $status = $response->status();
            Log::error('Lemon Squeezy checkout failed', [
                'status' => $status,
                'body' => $body,
                'plan_id' => $plan->id,
                'store_id_sent' => $storeId,
                'variant_id_sent' => $variantId,
            ]);

            if ($status === 404) {
                $isVariantError = isset($body['errors'][0]['source']['pointer'])
                    && str_contains($body['errors'][0]['source']['pointer'] ?? '', 'variant');
                if ($isVariantError) {
                    $msg = 'This plan is not set up for checkout. Please contact support.';
                    return $wantsJson
                        ? response()->json(['message' => $msg], 422)
                        : redirect()->route('client-area.checkout')->with('error', $msg);
                }
            }

            return $wantsJson
                ? response()->json(['message' => $genericError], 502)
                : redirect()->route('client-area.checkout')->with('error', $genericError);
        }

        $checkoutUrl = $body['data']['attributes']['url'] ?? null;

        if (empty($checkoutUrl)) {
            Log::error('Lemon Squeezy checkout response missing url', ['response' => $body]);
            return $wantsJson
                ? response()->json(['message' => $genericError], 502)
                : redirect()->route('client-area.checkout')->with('error', $genericError);
        }

        return $wantsJson
            ? response()->json(['redirect_url' => $checkoutUrl], 200)
            : redirect()->away($checkoutUrl);
    }

    /**
     * Single webhook endpoint: route by meta.event_name to the appropriate handler.
     * Verifies X-Signature (HMAC-SHA256) before processing.
     */
    public function webhook(Request $request): JsonResponse
    {
        $signingSecret = config('services.lemonsqueezy.signing_secret');
        if (empty($signingSecret)) {
            Log::error('Lemon Squeezy webhook: signing secret not configured');
            return response()->json(['status' => 'error', 'message' => 'Webhook not configured'], 500);
        }

        $signature = $request->header('X-Signature');
        $payload = $request->getContent();
        if (empty($signature) || empty($payload)) {
            Log::warning('Lemon Squeezy webhook: missing signature or body');
            return response()->json(['status' => 'error', 'message' => 'Invalid request'], 401);
        }

        $computed = hash_hmac('sha256', $payload, $signingSecret);
        if (! hash_equals($computed, $signature)) {
            Log::warning('Lemon Squeezy webhook: signature verification failed');
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401);
        }

        $data = $request->all();
        $eventName = $data['meta']['event_name'] ?? null;

        match ($eventName) {
            'order_created' => $this->orderCreated($request),
            'order_refunded' => $this->orderRefunded($request),
            'subscription_payment_failed' => $this->subscriptionPaymentFailed($request),
            default => $this->handleUnknownEvent($data, $eventName),
        };

        return response()->json(['status' => 'success', 'message' => 'Webhook received'], 200);
    }

    private function handleUnknownEvent(array $data, ?string $eventName): void
    {
        Log::info('Lemon Squeezy webhook: unhandled event', ['event_name' => $eventName, 'data' => $data]);
    }

    /**
     * Handle order_created: create Transaction, Invoice, Product and send product-created notification.
     *
     * Expected payload (from Lemon Squeezy):
     * - data.id: order id (e.g. "7469292")
     * - data.type: "orders"
     * - data.attributes: total (cents), tax, currency, status ("paid"), identifier (UUID), user_name, user_email, order_number, first_order_item, ...
     * - meta.event_name: "order_created"
     * - meta.custom_data: plan_id (UUID), user_id (string, from checkout custom data)
     *
     * Amounts in data.attributes are in cents (e.g. total: 900 = $9.00).
     */
    protected function orderCreated(Request $request): void
    {
        $data = $request->all();
        $payload = $data['data']['attributes'] ?? [];
        $orderId = (string) ($data['data']['id'] ?? '');
        $identifier = (string) ($payload['identifier'] ?? $orderId);
        $status = $payload['status'] ?? '';

        if ($status !== 'paid') {
            Log::info('Lemon Squeezy: order_created ignored (not paid)', ['order_id' => $orderId, 'status' => $status]);
            return;
        }

        $customData = $data['meta']['custom_data'] ?? [];
        $userId = $customData['user_id'] ?? null;
        $planId = $customData['plan_id'] ?? null;

        if (!$userId || !$planId) {
            Log::warning('Lemon Squeezy: order_created missing user_id or plan_id', ['custom_data' => $customData]);
            return;
        }

        if (Transaction::where('provider_transaction_id', $identifier)->where('provider', 'lemon_squeezy')->exists()) {
            Log::info('Lemon Squeezy: order_created already processed', ['identifier' => $identifier]);
            return;
        }

        $user = User::find((int) $userId);
        if (!$user) {
            Log::warning('Lemon Squeezy: order_created user not found', ['user_id' => $userId]);
            return;
        }

        $plan = Plan::find($planId);
        if (!$plan || !$plan->is_active) {
            Log::warning('Lemon Squeezy: order_created plan not found or inactive', ['plan_id' => $planId]);
            return;
        }

        $totalCents = (int) ($payload['total'] ?? 0);
        $taxCents = (int) ($payload['tax'] ?? 0);
        $total = $totalCents / 100;
        $taxAmount = $taxCents / 100;
        $subtotal = $total - $taxAmount;
        $currency = $payload['currency'] ?? 'USD';

        $billingName = $payload['user_name'] ?? $user->name;
        $billingEmail = $payload['user_email'] ?? $user->email;

        $user->update([
            'billing_name' => $billingName ?: $user->billing_name,
            'billing_email' => $billingEmail ?: $user->billing_email,
        ]);

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'transaction_id' => $identifier,
            'provider_transaction_id' => $identifier,
            'provider' => 'lemon_squeezy',
            'type' => 'payment',
            'amount' => $subtotal,
            'tax_amount' => $taxAmount,
            'currency' => $currency,
            'status' => 'completed',
            'payment_method' => 'card',
            'payment_method_details' => [
                'order_id' => $orderId,
                'order_number' => $payload['order_number'] ?? null,
                'first_order_item' => $payload['first_order_item'] ?? null,
            ],
            'billing_name' => $billingName,
            'billing_email' => $billingEmail,
            'billing_address' => $user->billing_address,
            'billing_city' => $user->billing_city,
            'billing_state' => $user->billing_state,
            'billing_country' => $user->billing_country,
            'billing_zip' => $user->billing_postal_code ?? '',
            'description' => 'Subscription purchase: ' . $plan->name,
            'meta' => $payload,
            'paid_at' => now(),
            'refunded_at' => null,
        ]);

        $invoiceItems = [
            [
                'description' => $plan->name . ' Plan - ' . ($plan->billing_cycle ?? 'Annual') . ' Subscription',
                'quantity' => 1,
                'price' => $subtotal,
            ],
        ];

        $invoice = Invoice::create([
            'user_id' => $user->id,
            'transaction_id' => $transaction->id,
            'type' => 'subscription',
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'currency' => $currency,
            'status' => 'paid',
            'issued_at' => now(),
            'due_at' => now(),
            'paid_at' => now(),
            'billing_name' => $billingName,
            'billing_email' => $billingEmail,
            'billing_address' => $user->billing_address ?? '',
            'billing_city' => $user->billing_city ?? '',
            'billing_state' => $user->billing_state ?? '',
            'billing_country' => $user->billing_country ?? '',
            'billing_zip' => $user->billing_postal_code ?? '',
            'items' => $invoiceItems,
            'meta' => [
                'plan_id' => $plan->id,
                'plan_slug' => $plan->slug,
                'plan_name' => $plan->name,
                'billing_cycle' => $plan->billing_cycle ?? 'annual',
                'order_id' => $orderId,
                'identifier' => $identifier,
                'lemon_squeezy_data' => $payload,
            ],
        ]);

        do {
            $randomNumber = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $productName = 'wp_' . $randomNumber;
        } while (Product::where('name', $productName)->exists());

        $createProduct = Product::create([
            'owner_id' => (string) $user->id,
            'owner_type' => 'App\Models\User',
            'users' => null,
            'invoice_id' => $invoice->id,
            'plan_id' => $plan->id,
            'name' => $productName,
            'server_capabilities' => $plan->capabilities ?? null,
            'server_limits' => $plan->capabilities ?? null,
            'status' => 'active',
            'server_type' => 'wordpress',
            'server_region' => 'eu-east-1',
            'meta' => $payload,
            'activated_at' => now(),
        ]);

        if ($createProduct) {
            try {
                app(NotificationService::class)->sendProductCreated($user, $createProduct, $invoice);
                Log::info('Lemon Squeezy: product created and notification sent', [
                    'user_id' => $user->id,
                    'product_id' => $createProduct->id,
                    'invoice_id' => $invoice->id,
                ]);
            } catch (\Throwable $e) {
                Log::error('Lemon Squeezy: failed to send product creation notification', [
                    'user_id' => $user->id,
                    'product_id' => $createProduct->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Handle order_refunded: log and optionally notify user.
     */
    protected function orderRefunded(Request $request): void
    {
        $data = $request->all();
        $payload = $data['data']['attributes'] ?? [];
        $customData = $data['meta']['custom_data'] ?? [];
        $userId = $customData['user_id'] ?? null;
        $orderId = $data['data']['id'] ?? null;

        Log::info('Lemon Squeezy: order_refunded', ['order_id' => $orderId]);

        if ($userId) {
            $user = User::find((int) $userId);
            if ($user) {
                try {
                    Mail::to($user->email)->send(new PaymentCancelledMail($user));
                } catch (\Throwable $e) {
                    Log::error('Lemon Squeezy: failed to send refund/cancelled email', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Handle subscription_payment_failed: send payment failed email to user.
     */
    protected function subscriptionPaymentFailed(Request $request): void
    {
        $data = $request->all();
        $payload = $data['data']['attributes'] ?? [];
        $customData = $data['meta']['custom_data'] ?? [];
        $userId = $customData['user_id'] ?? null;

        Log::info('Lemon Squeezy: subscription_payment_failed', ['data' => $data]);

        if ($userId) {
            $user = User::find((int) $userId);
            if ($user) {
                try {
                    $errorMessage = $payload['first_failure_reason'] ?? 'Payment could not be processed.';
                    Mail::to($user->email)->send(new PaymentFailedMail($user, $errorMessage));
                } catch (\Throwable $e) {
                    Log::error('Lemon Squeezy: failed to send payment failed email', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}