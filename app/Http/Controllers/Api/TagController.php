<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTagRequest;
use App\Models\Tag;
use App\Services\TagService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function __construct(private readonly TagService $tagService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $tags = $this->tagService->listForUser($request->user());

        $data = $tags->map(fn (Tag $tag) => $tag->only(['id', 'name']));

        return ApiResponse::success('Liste des tags.', $data);
    }

    public function store(StoreTagRequest $request): JsonResponse
    {
        $tag = $this->tagService->createForUser($request->user(), $request->validated());

        return ApiResponse::success('Tag créé.', $tag->only(['id', 'name']), 201);
    }
}