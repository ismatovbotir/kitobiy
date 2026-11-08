<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsConfirmed
{
    /**
     * Send signed-in users who have not confirmed their account in the Telegram
     * bot to the confirmation page, remembering where they were going.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->needsConfirmation()) {
            abort_if($request->expectsJson(), 403, __('Confirm your account first.'));

            return Redirect::guest(route('confirmation.notice'));
        }

        return $next($request);
    }
}
