<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class HandleLocale
{
    /**
     * Use the language the person chose (kept in the `locale` cookie), when
     * it is one the app supports; otherwise the configured default.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->cookie('locale');

        if (is_string($locale) && array_key_exists($locale, config('app.supported_locales', []))) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
