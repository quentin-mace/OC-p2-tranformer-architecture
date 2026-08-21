<?php

use App\Models\Tag;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('rejects unauthenticated access to tags (401)', function () {
    $this->getJson('/api/tags')->assertStatus(401);
});

it('lists only the tags of the current user, sorted by name', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();

    Tag::factory()->for($me)->create(['name' => 'travail']);
    Tag::factory()->for($me)->create(['name' => 'courses']);
    Tag::factory()->for($other)->create(['name' => 'tag-ennemi']);

    Sanctum::actingAs($me);

    $response = $this->getJson('/api/tags');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'courses')
        ->assertJsonPath('data.1.name', 'travail');
});

it('creates a tag', function () {
    $me = User::factory()->create();
    Sanctum::actingAs($me);

    $response = $this->postJson('/api/tags', ['name' => 'courses']);

    $response->assertStatus(201)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.name', 'courses')
        ->assertJsonStructure(['data' => ['id', 'name']]);

    expect(Tag::where('user_id', $me->id)->where('name', 'courses')->exists())->toBeTrue();
});

it('rejects a tag name already used by the same user (422)', function () {
    $me = User::factory()->create();
    Tag::factory()->for($me)->create(['name' => 'courses']);
    Sanctum::actingAs($me);

    $this->postJson('/api/tags', ['name' => 'courses'])
        ->assertStatus(422)
        ->assertJsonStructure(['data' => ['errors' => ['name']]]);
});

it('allows a tag name already used by another user', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    Tag::factory()->for($other)->create(['name' => 'courses']);

    Sanctum::actingAs($me);

    $this->postJson('/api/tags', ['name' => 'courses'])
        ->assertStatus(201);
});

it('rejects a tag longer than 50 characters (422)', function () {
    $me = User::factory()->create();
    Sanctum::actingAs($me);

    $this->postJson('/api/tags', ['name' => str_repeat('a', 51)])
        ->assertStatus(422)
        ->assertJsonStructure(['data' => ['errors' => ['name']]]);
});