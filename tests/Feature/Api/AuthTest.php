<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Config::set('auth.allowed_reset_hosts', ['http://front.local']);
});

it('registers a user and returns a token', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Account created successfully.')
        ->assertJsonPath('data.user.name', 'Ada Lovelace')
        ->assertJsonPath('data.user.email', 'ada@example.com')
        ->assertJsonPath('data.user.email_verified_at', null)
        ->assertJsonStructure(['data' => ['user' => ['id', 'name', 'email', 'email_verified_at'], 'token']]);

    expect(User::where('email', 'ada@example.com')->exists())->toBeTrue();
});

it('rejects registration when email is already taken', function () {
    User::factory()->create(['email' => 'ada@example.com']);

    $response = $this->postJson('/api/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('status', 'error')
        ->assertJsonStructure(['data' => ['errors' => ['email']]]);
});

it('rejects registration when password is not confirmed', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'mismatch',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('data.errors.password.0', fn ($msg) => is_string($msg));
});

it('logs in an existing user and returns a token', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $response = $this->postJson('/api/login', [
        'email' => 'ada@example.com',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonStructure(['data' => ['user' => ['id', 'name', 'email', 'email_verified_at'], 'token']]);
});

it('rejects login with wrong password (401)', function () {
    User::factory()->create(['email' => 'ada@example.com']);

    $response = $this->postJson('/api/login', [
        'email' => 'ada@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('status', 'error')
        ->assertJsonStructure(['data' => ['errors' => ['email']]]);
});

it('rate-limits login after 5 failed attempts', function () {
    User::factory()->create(['email' => 'ada@example.com']);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/login', [
            'email' => 'ada@example.com',
            'password' => 'wrong',
        ])->assertStatus(401);
    }

    $this->postJson('/api/login', [
        'email' => 'ada@example.com',
        'password' => 'wrong',
    ])->assertStatus(422)
        ->assertJsonStructure(['data' => ['errors' => ['email']]]);
});

it('logs out and revokes the current token', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/logout')
        ->assertOk()
        ->assertJsonPath('message', 'Logout successful.');
});

it('sends the same forgot-password response whether the email exists or not', function () {
    Notification::fake();
    User::factory()->create(['email' => 'ada@example.com']);

    $payload = ['redirect_url' => 'http://front.local/reset'];
    $known = $this->postJson('/api/forgot-password', ['email' => 'ada@example.com'] + $payload);
    $unknown = $this->postJson('/api/forgot-password', ['email' => 'nobody@example.com'] + $payload);

    $known->assertOk()->assertJsonPath('status', 'success');
    $unknown->assertOk()->assertJsonPath('status', 'success');
    expect($known->json('message'))->toBe($unknown->json('message'));
});

it('rejects forgot-password without redirect_url (422)', function () {
    User::factory()->create(['email' => 'ada@example.com']);

    $this->postJson('/api/forgot-password', ['email' => 'ada@example.com'])
        ->assertStatus(422)
        ->assertJsonStructure(['data' => ['errors' => ['redirect_url']]]);
});

it('rejects forgot-password when redirect_url origin is not whitelisted (422)', function () {
    User::factory()->create(['email' => 'ada@example.com']);

    $this->postJson('/api/forgot-password', [
        'email' => 'ada@example.com',
        'redirect_url' => 'https://evil.example.com/reset',
    ])->assertStatus(422)
        ->assertJsonStructure(['data' => ['errors' => ['redirect_url']]]);
});

it('sends the reset link with a token and resets the password end-to-end', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $this->postJson('/api/forgot-password', [
        'email' => 'ada@example.com',
        'redirect_url' => 'http://front.local/reset',
    ])->assertOk();

    $token = null;
    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use (&$token) {
        expect($notification->redirectUrl)->toBe('http://front.local/reset');
        $token = $notification->token;

        return true;
    });

    $this->postJson('/api/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-strong-password',
        'password_confirmation' => 'new-strong-password',
    ])->assertOk()->assertJsonPath('message', 'Password reset successfully.');

    expect(auth()->attempt(['email' => $user->email, 'password' => 'new-strong-password']))->toBeTrue();
});

it('rejects reset-password with an invalid token (422)', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $response = $this->postJson('/api/reset-password', [
        'token' => 'not-a-valid-token',
        'email' => $user->email,
        'password' => 'new-strong-password',
        'password_confirmation' => 'new-strong-password',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('status', 'error');
});