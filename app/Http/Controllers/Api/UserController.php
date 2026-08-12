<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\DeleteAccountRequest;
use App\Http\Requests\User\UpdatePasswordRequest;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Services\UserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly UserService $userService)
    {
    }

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success(
            'Utilisateur courant.',
            $request->user()->only(['id', 'name', 'email', 'email_verified_at']),
        );
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->userService->updateProfile($request->user(), $request->validated());

        return ApiResponse::success(
            'Profil mis à jour.',
            $user->only(['id', 'name', 'email', 'email_verified_at']),
        );
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $this->userService->updatePassword($request->user(), $request->validated()['password']);

        return ApiResponse::success('Mot de passe mis à jour.');
    }

    public function destroy(DeleteAccountRequest $request): JsonResponse
    {
        $this->userService->deleteAccount($request->user());

        return ApiResponse::success('Compte supprimé.');
    }
}