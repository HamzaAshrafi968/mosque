<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\QuranPageService;
use Illuminate\Http\JsonResponse;

class QuranPageController extends BaseApiController
{
    public function __construct(private readonly QuranPageService $pages) {}

    public function show(int $page): JsonResponse
    {
        return $this->success($this->pages->pagePayload($page));
    }
}
