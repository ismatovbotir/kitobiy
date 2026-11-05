<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the signed-in user's email as verified from the link in the mail,
     * then continue where they were going (e.g. an invitation).
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()->intended(route('dashboard', absolute: false))
            ->with('status', __('Your email is confirmed. Welcome to Kitobiy!'));
    }
}
