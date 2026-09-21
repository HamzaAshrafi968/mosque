<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function index(): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /teacher',
            'Disallow: /student',
            'Disallow: /guardian',
            'Disallow: /super-admin',
            'Disallow: /api/',
            'Disallow: /login',
            'Disallow: /notifications',
            'Disallow: /quran/pages/',
            '',
            'Sitemap: '.url('/sitemap.xml'),
        ];

        return response(implode("\n", $lines)."\n")
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
