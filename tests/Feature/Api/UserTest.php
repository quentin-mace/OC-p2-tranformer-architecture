<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

it('rejects unauthenticated access to the user endpoint (401)', function () {
    $this->getJson('/api/user')->assertStatus(401);
});

it('shows the current user', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.name', $user->name)
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonStructure(['data' => ['id', 'name', 'email', 'email_verified_at']]);
});

it('updates name and email of the current user', function () {
    $user = User::factory()->create(['name' => 'Ada', 'email' => 'ada@example.com']);
    Sanctum::actingAs($user);

    $this->putJson('/api/user/profile', [
        'name' => 'Ada King',
        'email' => 'ada@example.com',
    ])->assertOk()
        ->assertJsonPath('data.name', 'Ada King');

    expect($user->fresh()->name)->toBe('Ada King');
});

it('resets email_verified_at when email changes', function () {
    $user = User::factory()->create([
        'email' => 'ada@example.com',
        'email_verified_at' => now(),
    ]);
    Sanctum::actingAs($user);

    $this->putJson('/api/user/profile', [
        'name' => $user->name,
        'email' => 'ada.king@example.com',
    ])->assertOk()
        ->assertJsonPath('data.email_verified_at', null);

    expect($user->fresh()->email_verified_at)->toBeNull();
});

it('rejects profile update if email is taken by another user (422)', function () {
    User::factory()->create(['email' => 'busy@example.com']);
    $user = User::factory()->create(['email' => 'ada@example.com']);
    Sanctum::actingAs($user);

    $this->putJson('/api/user/profile', [
        'name' => $user->name,
        'email' => 'busy@example.com',
    ])->assertStatus(422)
        ->assertJsonStructure(['data' => ['errors' => ['email']]]);
});

it('updates the password when current_password is correct', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->putJson('/api/user/password', [
        'current_password' => 'password',
        'password' => 'new-strong-password',
        'password_confirmation' => 'new-strong-password',
    ])->assertOk();

    expect(Hash::check('new-strong-password', $user->fresh()->password))->toBeTrue();
});

it('rejects password update when current_password is wrong (422)', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->putJson('/api/user/password', [
        'current_password' => 'wrong',
        'password' => 'new-strong-password',
        'password_confirmation' => 'new-strong-password',
    ])->assertStatus(422)
        ->assertJsonStructure(['data' => ['errors' => ['current_password']]]);
});

it('deletes the account and revokes all tokens', function () {
    $user = User::factory()->create();
    $user->createToken('one');
    $user->createToken('two');
    Sanctum::actingAs($user);

    $this->deleteJson('/api/user', ['password' => 'password'])
        ->assertOk()
        ->assertJsonPath('message', 'Compte supprimé.');

    expect(User::find($user->id))->toBeNull();
});

it('rejects account deletion when password is wrong (422)', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->deleteJson('/api/user', ['password' => 'wrong'])
        ->assertStatus(422)
        ->assertJsonStructure(['data' => ['errors' => ['password']]]);

    expect(User::find($user->id))->not->toBeNull();
});