<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreTagRequest;
use App\Http\Resources\TagResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController
{
    public function index(Request $request): JsonResponse
    {
        $tags = $request->user()->tags()->orderBy('name')->get();

        return ApiResponse::success('Liste des tags.', TagResource::collection($tags));
    }

    public function store(StoreTagRequest $request): JsonResponse
    {
        $tag = $request->user()->tags()->create($request->validated());

        return ApiResponse::success('Tag créé.', new TagResource($tag), 201);
    }
}