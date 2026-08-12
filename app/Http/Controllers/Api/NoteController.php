<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNoteRequest;
use App\Models\Note;
use App\Services\NoteService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function __construct(private readonly NoteService $noteService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $notes = $this->noteService->listForUser($request->user());

        $data = $notes->map(fn (Note $note) => [
            'id' => $note->id,
            'text' => $note->text,
            'tag' => [
                'id' => $note->tag->id,
                'name' => $note->tag->name,
            ],
            'created_at' => $note->created_at,
        ]);

        return ApiResponse::success('Liste des notes.', $data);
    }

    public function store(StoreNoteRequest $request): JsonResponse
    {
        $note = $this->noteService->createForUser($request->user(), $request->validated());

        return ApiResponse::success('Note créée.', [
            'id' => $note->id,
            'text' => $note->text,
            'tag_id' => $note->tag_id,
            'created_at' => $note->created_at,
        ], 201);
    }

    public function destroy(Request $request, int $note): JsonResponse
    {
        $this->noteService->deleteForUser($request->user(), $note);

        return ApiResponse::success('Note supprimée.');
    }
}