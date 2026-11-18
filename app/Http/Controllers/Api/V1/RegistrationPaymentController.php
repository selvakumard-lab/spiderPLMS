<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterTenantRequest;
use App\Http\Resources\TenantResource;
use App\Http\Resources\UserResource;
use App\Models\ActivityLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Services\RazorpayService;
use App\Services\TenantRegistrationService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Tenant sign-up paid through Razorpay: 1) create an order for the chosen plan,
 * 2) after Checkout succeeds, verify the signature and only then create the workspace.
 */
class RegistrationPaymentController extends Controller
{
    private const HOLD_MINUTES = 60;

    public function order(RegisterTenantRequest $request, RazorpayService $razorpay): JsonResponse
    {
        abort_unless(SystemSetting::get('registration_open'), 403, 'New workspace sign-ups are currently closed.');

        $data = $request->validated();
        $plan = Plan::where('code', $data['plan'])->firstOrFail();
        $price = $plan->monthlyPriceFor($data['country_code'] ?? null);

        try {
            $order = $razorpay->createOrder($price['amount'], $price['currency'], 'reg_'.now()->timestamp, [
                'workspace' => $data['slug'],
                'plan' => $plan->code,
            ]);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['plan' => $e->getMessage()]);
        }

        Cache::put($this->cacheKey($order['id']), [...$data, 'currency' => $price['currency']], now()->addMinutes(self::HOLD_MINUTES));

        return response()->json([
            'key' => $razorpay->keyId(),
            'order_id' => $order['id'],
            'amount' => $order['amount'],
            'currency' => $order['currency'],
            'plan' => $plan->name,
            'name' => $data['organization'],
            'email' => $data['email'],
            'contact' => $data['phone'] ?? null,
        ]);
    }

    public function verify(Request $request, RazorpayService $razorpay, TenantRegistrationService $registration): JsonResponse
    {
        $input = $request->validate([
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);

        if (! $razorpay->isValidSignature($input['razorpay_order_id'], $input['razorpay_payment_id'], $input['razorpay_signature'])) {
            throw ValidationException::withMessages(['payment' => 'Payment could not be verified.']);
        }

        $data = Cache::pull($this->cacheKey($input['razorpay_order_id']));
        if ($data === null) {
            throw ValidationException::withMessages(['payment' => 'This registration has expired or was already completed.']);
        }

        ['tenant' => $tenant, 'user' => $user] = $registration->register([...$data, 'status' => 'active']);

        Subscription::where('tenant_id', $tenant->id)->update([
            'gateway' => 'razorpay',
            'gateway_subscription_id' => $input['razorpay_payment_id'],
        ]);
        ActivityLog::record('tenant.payment', $tenant, ['gateway' => 'razorpay', 'payment' => $input['razorpay_payment_id']], $user->id);

        app(TenantContext::class)->set($tenant);
        $user->load('roles');

        return response()->json([
            'token' => $user->createToken('web')->plainTextToken,
            'user' => new UserResource($user),
            'tenant' => new TenantResource($tenant->load('plan')),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ], 201);
    }

    private function cacheKey(string $orderId): string
    {
        return 'registration:'.$orderId;
    }
}
