<?php

namespace App\Http\Controllers;

use App\Services\QuranPageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuranPageController extends Controller
{
    public function __construct(private readonly QuranPageService $pages) {}

    public function show(int $page): View
    {
        $page = $this->clamp($page);

        return view('quran.pages.show', [
            'page' => $page,
            'ayahs' => $this->pages->ayahsForPage($page),
            'surahStarts' => $this->pages->surahStartsOnPage($page),
            'maxPage' => $this->pages->maxPage(),
            'pagesSeeded' => $this->pages->pagesAreSeeded(),
        ]);
    }

    public function preview(Request $request, int $page): View
    {
        $page = $this->clamp($page);
        $to = max($page, (int) $request->integer('to', $page));
        $to = min($to, $page + 19, $this->pages->maxPage());

        return view('quran.pages.preview', [
            'pages' => $this->pages->pagesForRange($page, $to),
            'pagesSeeded' => $this->pages->pagesAreSeeded(),
        ]);
    }

    public function json(int $page): JsonResponse
    {
        return response()->json($this->pages->pagePayload($page));
    }

    private function clamp(int $page): int
    {
        return max(1, min($page, $this->pages->maxPage()));
    }
}
