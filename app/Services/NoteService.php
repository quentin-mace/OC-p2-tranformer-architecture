<?php

namespace App\Services;

use App\Models\Note;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class NoteService
{
    public function listForUser(User $user): Collection
    {
        return Note::with('tag')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();
    }

    public function createForUser(User $user, array $data): Note
    {
        $tagBelongsToUser = Tag::where('id', $data['tag_id'])
            ->where('user_id', $user->id)
            ->exists();

        if (! $tagBelongsToUser) {
            throw ValidationException::withMessages([
                'tag_id' => 'Ce tag est introuvable.',
            ]);
        }

        return Note::create([
            'user_id' => $user->id,
            'tag_id' => $data['tag_id'],
            'text' => $data['text'],
        ]);
    }

    public function deleteForUser(User $user, int $noteId): void
    {
        $note = Note::where('user_id', $user->id)
            ->where('id', $noteId)
            ->firstOrFail();

        $note->delete();
    }
}