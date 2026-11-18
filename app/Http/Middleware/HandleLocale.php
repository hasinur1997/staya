<?php

namespace App\Http\Middleware;

use App\Models\MessSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class HandleLocale
{
    /**
     * Use the language the person chose (kept in the `locale` cookie), when
     * it is one the app supports; otherwise the default an Admin set in the
     * mess settings, falling back to the configured one.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('app.supported_locales', []);
        $locale = $request->cookie('locale');

        if (! is_string($locale) || ! array_key_exists($locale, $supported)) {
            $locale = MessSetting::defaultLocale();
        }

        App::setLocale($locale);

        return $next($request);
    }
}
