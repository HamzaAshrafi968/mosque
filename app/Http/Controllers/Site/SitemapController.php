<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $mosques = Tenant::query()
            ->publiclyVisible()
            ->whereNotNull('code')
            ->orderBy('name')
            ->get();

        return response()
            ->view('site.sitemap', ['mosques' => $mosques])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
