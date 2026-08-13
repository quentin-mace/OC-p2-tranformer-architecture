<?php

use App\Models\Note;
use App\Models\Tag;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('rejects unauthenticated access to notes (401)', function () {
    $this->getJson('/api/notes')->assertStatus(401);
});

it('lists only the notes of the current user', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();

    $myTag = Tag::factory()->for($me)->create();
    $otherTag = Tag::factory()->for($other)->create();

    Note::factory()->for($me)->for($myTag)->create(['text' => 'ma note']);
    Note::factory()->for($other)->for($otherTag)->create(['text' => 'note ennemie']);

    Sanctum::actingAs($me);

    $response = $this->getJson('/api/notes');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.text', 'ma note')
        ->assertJsonPath('data.0.tag.id', $myTag->id)
        ->assertJsonPath('data.0.tag.name', $myTag->name);
});

it('returns notes sorted by created_at desc', function () {
    $me = User::factory()->create();
    $tag = Tag::factory()->for($me)->create();

    $older = Note::factory()->for($me)->for($tag)->create(['created_at' => now()->subDay()]);
    $newer = Note::factory()->for($me)->for($tag)->create(['created_at' => now()]);

    Sanctum::actingAs($me);

    $this->getJson('/api/notes')
        ->assertOk()
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.1.id', $older->id);
});

it('creates a note and returns it with tag_id (not tag object)', function () {
    $me = User::factory()->create();
    $tag = Tag::factory()->for($me)->create();

    Sanctum::actingAs($me);

    $response = $this->postJson('/api/notes', [
        'text' => 'Acheter du café',
        'tag_id' => $tag->id,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Note créée.')
        ->assertJsonPath('data.text', 'Acheter du café')
        ->assertJsonPath('data.tag_id', $tag->id)
        ->assertJsonMissingPath('data.tag');
});

it('rejects note creation with a tag belonging to another user (422)', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    $otherTag = Tag::factory()->for($other)->create();

    Sanctum::actingAs($me);

    $this->postJson('/api/notes', [
        'text' => 'test',
        'tag_id' => $otherTag->id,
    ])->assertStatus(422)
        ->assertJsonStructure(['data' => ['errors' => ['tag_id']]]);
});

it('rejects note creation with an inexistent tag_id (422)', function () {
    $me = User::factory()->create();

    Sanctum::actingAs($me);

    $this->postJson('/api/notes', [
        'text' => 'test',
        'tag_id' => 9999,
    ])->assertStatus(422)
        ->assertJsonStructure(['data' => ['errors' => ['tag_id']]]);
});

it('rejects note creation without text (422)', function () {
    $me = User::factory()->create();
    $tag = Tag::factory()->for($me)->create();

    Sanctum::actingAs($me);

    $this->postJson('/api/notes', ['tag_id' => $tag->id])
        ->assertStatus(422)
        ->assertJsonStructure(['data' => ['errors' => ['text']]]);
});

it('deletes an owned note', function () {
    $me = User::factory()->create();
    $tag = Tag::factory()->for($me)->create();
    $note = Note::factory()->for($me)->for($tag)->create();

    Sanctum::actingAs($me);

    $this->deleteJson("/api/notes/{$note->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Note supprimée.');

    expect(Note::find($note->id))->toBeNull();
});

it('returns 404 when deleting a note belonging to another user', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    $otherTag = Tag::factory()->for($other)->create();
    $otherNote = Note::factory()->for($other)->for($otherTag)->create();

    Sanctum::actingAs($me);

    $this->deleteJson("/api/notes/{$otherNote->id}")
        ->assertStatus(404)
        ->assertJsonPath('status', 'error');

    expect(Note::find($otherNote->id))->not->toBeNull();
});

it('returns 404 when deleting a non-existent note', function () {
    $me = User::factory()->create();
    Sanctum::actingAs($me);

    $this->deleteJson('/api/notes/9999')
        ->assertStatus(404);
});