<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Support\ApiResponse;
use App\Support\AuthResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Authentication
 *
 * Register, log in, log out, password reset, and email verification endpoints.
 */
class AuthController
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    /**
     * Register
     *
     * Creates an account, issues a bearer token, and triggers the email verification flow.
     *
     * @unauthenticated
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return ApiResponse::success('Compte créé avec succès.', $this->authPayload($result), 201);
    }

    /**
     * Log in
     *
     * Authenticates credentials and returns a bearer token. Rate limited to 5 attempts
     * per email+IP; further attempts return a 422 throttle error.
     *
     * @unauthenticated
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated(), $request);

        return ApiResponse::success('Connexion réussie.', $this->authPayload($result));
    }

    /**
     * Log out
     *
     * Revokes the current bearer token.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request);

        return ApiResponse::success('Déconnexion réussie.');
    }

    /**
     * Request password reset
     *
     * Sends a password reset email if the account exists. The response is intentionally
     * identical for known and unknown emails (anti-enumeration).
     *
     * `redirect_url` is the URL of the client page that will collect the new password
     * and POST it to `/api/reset-password`. Its origin (scheme+host+port) must be listed
     * in the server's `ALLOWED_RESET_HOSTS` whitelist, otherwise the request is rejected.
     *
     * @unauthenticated
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->authService->forgotPassword($data['email'], $data['redirect_url']);

        return ApiResponse::success('Un lien de réinitialisation sera envoyé si le compte existe.');
    }

    /**
     * Reset password
     *
     * Consumes the token received by email and sets a new password. All existing tokens
     * for the user are revoked on success.
     *
     * @unauthenticated
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->authService->resetPassword($request->validated());

        return ApiResponse::success('Mot de passe réinitialisé avec succès.');
    }

    /**
     * Resend email verification
     *
     * Sends a fresh verification email to the authenticated user. Idempotent if the address
     * is already verified. Rate limited to 6 requests per minute.
     */
    public function sendVerificationEmail(Request $request): JsonResponse
    {
        $this->authService->sendVerificationEmail($request);

        return ApiResponse::success('Un nouveau lien de vérification a été envoyé.');
    }

    /**
     * Verify email
     *
     * Endpoint hit by the signed link in the verification email. Authentication is provided
     * by the signature itself, not by a bearer token.
     *
     * @unauthenticated
     *
     * @urlParam id integer required The user ID. Example: 1
     * @urlParam hash string required The SHA-1 hash of the user's email. Example: abc123
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $this->authService->verifyEmail($request);

        return ApiResponse::success('Adresse email vérifiée.', ['verified' => true]);
    }

    private function authPayload(AuthResult $result): array
    {
        return [
            'user' => new UserResource($result->user),
            'token' => $result->token,
        ];
    }
}