<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return ApiResponse::success('Compte créé avec succès.', [
            'user' => $result['user']->only(['id', 'name', 'email', 'email_verified_at']),
            'token' => $result['token'],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated(), $request);

        return ApiResponse::success('Connexion réussie.', [
            'user' => $result['user']->only(['id', 'name', 'email', 'email_verified_at']),
            'token' => $result['token'],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request);

        return ApiResponse::success('Déconnexion réussie.');
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->forgotPassword($request->validated()['email']);

        return ApiResponse::success('Un lien de réinitialisation sera envoyé si le compte existe.');
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->authService->resetPassword($request->validated());

        return ApiResponse::success('Mot de passe réinitialisé avec succès.');
    }

    public function sendVerificationEmail(Request $request): JsonResponse
    {
        $this->authService->sendVerificationEmail($request);

        return ApiResponse::success('Un nouveau lien de vérification a été envoyé.');
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $this->authService->verifyEmail($request);

        return ApiResponse::success('Adresse email vérifiée.', ['verified' => true]);
    }
}