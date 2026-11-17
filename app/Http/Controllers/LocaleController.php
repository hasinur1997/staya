<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateLocaleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cookie;

class LocaleController extends Controller
{
    /**
     * Remember the chosen language for a year and return to the page.
     */
    public function update(UpdateLocaleRequest $request): RedirectResponse
    {
        Cookie::queue(Cookie::make('locale', $request->string('locale')->toString(), 60 * 24 * 365, sameSite: 'lax'));

        return back();
    }
}
