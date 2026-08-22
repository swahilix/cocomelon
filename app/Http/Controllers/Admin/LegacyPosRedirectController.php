<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class LegacyPosRedirectController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return redirect()
            ->route('admin.pos.index')
            ->with('info', 'The legacy stock POS has been retired. Use the dedicated POS terminal.');
    }
}
