<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ForgotPasswordOtpRequest;
use App\Http\Requests\Auth\VerifyPasswordResetOtpRequest;
use App\Http\Requests\Auth\ResetPasswordOtpRequest;
use App\Services\Auth\PasswordResetOtpService;
use App\Services\Auth\MemberInvitationService;
use App\Http\Requests\Auth\AcceptInvitationRequest;
use App\Support\TenantAuthorizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Tenant;
use App\Models\User;

class AuthController extends Controller
{
    public function __construct(
        private readonly PasswordResetOtpService $passwordResetOtps,
        private readonly MemberInvitationService $memberInvitations,
        private readonly TenantAuthorizer $authorizer,
    ) {}

    public function forgotPassword(ForgotPasswordOtpRequest $request): JsonResponse
    {
        $this->passwordResetOtps->request($request->validated('email'));

        return response()->json(['message' => 'If that email exists, a reset code has been sent.']);
    }

    public function verifyPasswordOtp(VerifyPasswordResetOtpRequest $request): JsonResponse
    {
        $this->passwordResetOtps->verify($request->validated('email'), $request->validated('otp'));

        return response()->json(['message' => 'Code verified.']);
    }

    public function resetPassword(ResetPasswordOtpRequest $request): JsonResponse
    {
        $this->passwordResetOtps->reset(
            $request->validated('email'),
            $request->validated('otp'),
            $request->validated('password'),
        );

        return response()->json(['message' => 'Password reset successfully.']);
    }

    public function acceptInvitation(AcceptInvitationRequest $request): JsonResponse
    {
        $this->memberInvitations->accept($request->validated('token'), $request->validated('password'));

        return response()->json(['message' => 'Password created successfully.']);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $request->authenticate();
        
        $user = $request->user();
        $token = $user->createToken('api-token')->plainTextToken;
        [$tenants, $branches] = $this->memberships($user);
        
        return response()->json([
            'user' => $user,
            'tenants' => $tenants,
            'branches' => $branches,
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        
        return response()->json(['message' => 'Logged out']);
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->user();
        [$tenants, $branches] = $this->memberships($user);

        return response()->json([
            'user' => $user,
            'tenants' => $tenants,
            'branches' => $branches,
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();
        $token = $user->createToken('api-token')->plainTextToken;
        
        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    private function memberships(User $user): array
    {
        $tenants = $user->tenants()->wherePivot('is_active', true)->get();

        $tenants->each(function (Tenant $tenant) use ($user): void {
            $tenant->setRelation('branches', $this->authorizer->allowedBranches($user, $tenant));
        });

        $branches = $tenants
            ->flatMap(fn ($tenant) => $tenant->branches)
            ->unique('id')
            ->sortBy('name')
            ->values();

        return [$tenants, $branches];
    }
}
