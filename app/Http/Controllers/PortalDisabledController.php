<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PortalDisabledController extends Controller
{
    public function __invoke(): View
    {
        return view('portal-disabled');
    }
}
