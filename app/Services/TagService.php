<?php

namespace App\Services;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class TagService
{
    public function listForUser(User $user): Collection
    {
        return Tag::where('user_id', $user->id)
            ->orderBy('name')
            ->get();
    }

    public function createForUser(User $user, array $data): Tag
    {
        return Tag::create([
            'user_id' => $user->id,
            'name' => $data['name'],
        ]);
    }
}