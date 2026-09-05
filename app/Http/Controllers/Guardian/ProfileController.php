<?php

namespace App\Http\Controllers\Guardian;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends BaseGuardianController
{
    public function show(Request $request): View
    {
        return view('guardian.profile', [
            'guardian' => $this->currentGuardian($request),
            'user' => $request->user(),
        ]);
    }
}
