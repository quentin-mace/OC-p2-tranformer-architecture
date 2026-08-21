<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\User\DeleteAccountRequest;
use App\Http\Requests\User\UpdatePasswordRequest;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group User
 *
 * Endpoints for managing the authenticated user's own profile and account.
 */
class UserController
{
    /**
     * Get current user
     *
     * Returns the profile of the authenticated user.
     */
    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success('Current user.', new UserResource($request->user()));
    }

    /**
     * Update profile
     *
     * Updates the user's name and email. Changing the email clears `email_verified_at`
     * (the new address must be verified again).
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if ($user->email !== $data['email']) {
            $user->email_verified_at = null;
        }

        $user->fill($data)->save();

        return ApiResponse::success('Profile updated.', new UserResource($user));
    }

    /**
     * Update password
     *
     * Replaces the password. Requires the current password for confirmation.
     *
     * @bodyParam password_confirmation string required Must match `password`. Example: Str0ngP@ssw0rd!
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $request->user()->update([
            'password' => $request->validated()['password'],
        ]);

        return ApiResponse::success('Password updated.');
    }

    /**
     * Delete account
     *
     * Deletes the authenticated user's account, cascades all their notes and tags,
     * and revokes every issued token. Requires the current password.
     */
    public function destroy(DeleteAccountRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->tokens()->delete();
        $user->delete();

        return ApiResponse::success('Account deleted.');
    }
}