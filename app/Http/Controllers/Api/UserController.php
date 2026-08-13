<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\User\DeleteAccountRequest;
use App\Http\Requests\User\UpdatePasswordRequest;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController
{
    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success('Utilisateur courant.', new UserResource($request->user()));
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if ($user->email !== $data['email']) {
            $user->email_verified_at = null;
        }

        $user->fill($data)->save();

        return ApiResponse::success('Profil mis à jour.', new UserResource($user));
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $request->user()->update([
            'password' => $request->validated()['password'],
        ]);

        return ApiResponse::success('Mot de passe mis à jour.');
    }

    public function destroy(DeleteAccountRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->tokens()->delete();
        $user->delete();

        return ApiResponse::success('Compte supprimé.');
    }
}