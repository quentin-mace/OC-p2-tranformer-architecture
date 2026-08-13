<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreNoteRequest;
use App\Http\Resources\NoteResource;
use App\Models\Note;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoteController
{
    public function index(Request $request): JsonResponse
    {
        $notes = $request->user()->notes()
            ->with('tag')
            ->orderByDesc('created_at')
            ->get();

        return ApiResponse::success('Liste des notes.', NoteResource::collection($notes));
    }

    public function store(StoreNoteRequest $request): JsonResponse
    {
        $note = $request->user()->notes()->create($request->validated());

        return ApiResponse::success('Note créée.', new NoteResource($note), 201);
    }

    public function destroy(Note $note): JsonResponse
    {
        $note->delete();

        return ApiResponse::success('Note supprimée.');
    }
}