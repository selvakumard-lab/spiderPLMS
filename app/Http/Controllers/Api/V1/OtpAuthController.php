<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Http\Resources\UserResource;
use App\Mail\LoginCodeMail;
use App\Models\ActivityLog;
use App\Models\LoginOtp;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Email-OTP sign-in for every tenant user and for the Super Admin (no workspace).
 */
class OtpAuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    /**
     * Public: the tenant's branded login page needs to know the workspace exists.
     */
    public function workspace(string $slug): JsonResponse
    {
        $tenant = Tenant::whereRaw('lower(slug) = ?', [Str::lower($slug)])->firstOrFail();

        return response()->json(['name' => $tenant->name, 'slug' => $tenant->slug]);
    }

    /**
     * Send a one-time code. Wrong or unknown details are reported so the person can fix them.
     */
    public function request(Request $request): JsonResponse
    {
        $data = $request->validate([
            'workspace' => ['nullable', 'string', 'max:40'],
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
        ], $this->messages());

        $workspace = $data['workspace'] ?? null;

        if (filled($workspace) && Tenant::whereRaw('lower(slug) = ?', [Str::lower($workspace)])->doesntExist()) {
            throw ValidationException::withMessages(['workspace' => 'We could not find that workspace. Check the sign-in link you were given.']);
        }

        $user = $this->findUser($workspace, $data['email']);

        if ($user === null) {
            throw ValidationException::withMessages(['email' => filled($workspace)
                ? 'No account uses this email in this workspace. Check the address or ask your admin for access.'
                : 'This email is not a platform administrator account.']);
        }

        if ($user->status === 'disabled') {
            throw ValidationException::withMessages(['email' => 'This account has been disabled. Contact your workspace admin.']);
        }

        $code = (string) random_int(100000, 999999);

        LoginOtp::where('user_id', $user->id)->whereNull('used_at')->update(['used_at' => now()]);
        LoginOtp::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes((int) config('plms.otp_ttl_minutes')),
        ]);

        $this->sendCode($user, $code, $request);

        return response()->json(['message' => 'A sign-in code has been sent to your email.']);
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'workspace' => ['nullable', 'string', 'max:40'],
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            'code' => ['required', 'digits:6'],
        ], $this->messages());

        $user = $this->findUser($data['workspace'] ?? null, $data['email']);
        $otp = $user ? LoginOtp::where('user_id', $user->id)->whereNull('used_at')->latest('id')->first() : null;

        if ($user === null) {
            throw ValidationException::withMessages(['email' => 'No account uses this email here. Go back and check the address.']);
        }

        if ($otp === null || $otp->expires_at->isPast()) {
            throw ValidationException::withMessages(['code' => 'This code has expired or was never requested. Request a new one.']);
        }

        if ($otp->attempts >= self::MAX_ATTEMPTS) {
            throw ValidationException::withMessages(['code' => 'Too many wrong attempts. Request a new code.']);
        }

        if (! Hash::check($data['code'], $otp->code_hash)) {
            $otp->increment('attempts');
            $left = max(0, self::MAX_ATTEMPTS - $otp->attempts);

            throw ValidationException::withMessages(['code' => "Incorrect code. {$left} ".($left === 1 ? 'attempt' : 'attempts').' left.']);
        }

        $otp->forceFill(['used_at' => now()])->save();
        $user->forceFill(['last_login_at' => now(), 'email_verified_at' => $user->email_verified_at ?? now()])->save();

        $tenant = $user->tenant;
        if ($tenant !== null) {
            app(TenantContext::class)->set($tenant);
            ActivityLog::record('auth.login', $user, userId: $user->id);
        }

        return $this->session($user, $tenant);
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'email.required' => 'Enter your email address.',
            'email.email' => 'Enter a valid email address, like name@company.com.',
            'email.max' => 'That email address is too long.',
            'code.required' => 'Enter the 6-digit code.',
            'code.digits' => 'The code is 6 digits. Check it and try again.',
        ];
    }

    private function sendCode(User $user, string $code, Request $request): void
    {
        $appName = (string) SystemSetting::get('app_name');
        $timezone = $user->tenant?->timezone ?: 'UTC';

        try {
            Mail::to($user->email)->send(new LoginCodeMail(
                code: $code,
                appName: $appName,
                where: $user->tenant?->name ?? $appName.' platform',
                expiresInMinutes: (int) config('plms.otp_ttl_minutes'),
                supportEmail: (string) SystemSetting::get('support_email'),
                signInUrl: url($user->tenant ? '/'.$user->tenant->slug : '/superAdmin'),
                audience: $user->isSuperAdmin() ? 'platform' : ($user->isClient() ? 'client' : 'team'),
                recipientEmail: $user->email,
                requestedAt: now($timezone)->format('j M Y, g:i A T'),
                ipAddress: $request->ip(),
            ));
        } catch (\Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['email' => 'We could not send the email right now. Please try again shortly.']);
        }
    }

    /** A null/blank workspace means the platform Super Admin. */
    private function findUser(?string $workspace, string $email): ?User
    {
        $email = Str::lower($email);

        if ($workspace === null || $workspace === '') {
            return User::withoutGlobalScopes()->whereNull('tenant_id')->where('is_super_admin', true)->where('email', $email)->first();
        }

        $tenant = Tenant::whereRaw('lower(slug) = ?', [Str::lower($workspace)])->first();

        return $tenant === null ? null : User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->where('email', $email)->first();
    }

    private function session(User $user, ?Tenant $tenant): JsonResponse
    {
        $user->load('roles');

        return response()->json([
            'token' => $user->createToken('web')->plainTextToken,
            'user' => new UserResource($user),
            'tenant' => $tenant ? new TenantResource($tenant->load('plan')) : null,
            'permissions' => $user->isSuperAdmin() ? ['*'] : $user->getAllPermissions()->pluck('name'),
        ]);
    }
}
