<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps applicants (pending or rejected registrations) out of the app.
 * They may only verify their email, see their registration status and sign
 * out. Staff accounts without a member profile are not affected.
 */
class EnsureRegistrationApproved
{
    /**
     * Routes an applicant may still use.
     *
     * @var list<string>
     */
    private const array ALLOWED_ROUTES = [
        'registration.status',
        'logout',
        'verification.*',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isApplicant() || $request->routeIs(...self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        abort_if($request->expectsJson(), 403, __('Your registration has not been approved.'));

        return to_route('registration.status');
    }
}
