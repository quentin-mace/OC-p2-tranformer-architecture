<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreNoteRequest;
use App\Http\Resources\NoteResource;
use App\Models\Note;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Notes
 *
 * Endpoints for the authenticated user's notes. Each note belongs to exactly one tag.
 */
class NoteController
{
    /**
     * List notes
     *
     * Returns notes owned by the authenticated user, ordered by creation date (newest first).
     * Each note is returned with its tag as a nested object.
     */
    public function index(Request $request): JsonResponse
    {
        $notes = $request->user()->notes()
            ->with('tag')
            ->orderByDesc('created_at')
            ->get();

        return ApiResponse::success('Liste des notes.', NoteResource::collection($notes));
    }

    /**
     * Create a note
     *
     * The `tag_id` must reference a tag owned by the authenticated user, otherwise the request
     * is rejected with a 422. The response contains `tag_id` (not the nested tag object).
     */
    public function store(StoreNoteRequest $request): JsonResponse
    {
        $note = $request->user()->notes()->create($request->validated());

        return ApiResponse::success('Note créée.', new NoteResource($note), 201);
    }

    /**
     * Delete a note
     *
     * A 404 is returned if the note does not exist or does not belong to the authenticated user
     * (the API never confirms the existence of another user's resources).
     *
     * @urlParam note integer required The note ID. Example: 12
     */
    public function destroy(Note $note): JsonResponse
    {
        $note->delete();

        return ApiResponse::success('Note supprimée.');
    }
}