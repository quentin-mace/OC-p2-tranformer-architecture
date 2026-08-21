<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'text' => $this->text,
            'tag' => $this->whenLoaded('tag', fn () => [
                'id' => $this->tag->id,
                'name' => $this->tag->name,
            ]),
            'tag_id' => $this->when(! $this->relationLoaded('tag'), $this->tag_id),
            'created_at' => $this->created_at,
        ];
    }
}