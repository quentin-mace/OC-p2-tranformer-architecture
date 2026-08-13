<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreTagRequest;
use App\Http\Resources\TagResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Tags
 *
 * Endpoints for the authenticated user's tags. Tags are personal: each user has their own set,
 * and tag names are unique per user.
 */
class TagController
{
    /**
     * List tags
     *
     * Returns tags owned by the authenticated user, sorted alphabetically.
     */
    public function index(Request $request): JsonResponse
    {
        $tags = $request->user()->tags()->orderBy('name')->get();

        return ApiResponse::success('Liste des tags.', TagResource::collection($tags));
    }

    /**
     * Create a tag
     *
     * A tag name must be unique among the authenticated user's own tags (another user can have
     * a tag with the same name).
     */
    public function store(StoreTagRequest $request): JsonResponse
    {
        $tag = $request->user()->tags()->create($request->validated());

        return ApiResponse::success('Tag créé.', new TagResource($tag), 201);
    }
}