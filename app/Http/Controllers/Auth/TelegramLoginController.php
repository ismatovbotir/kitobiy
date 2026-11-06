<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Telegram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TelegramLoginController extends Controller
{
    /**
     * Where Telegram's Login Widget sends the visitor back, with data signed by
     * Telegram. It logs in only an account that already connected this Telegram
     * from its profile; it never creates accounts, because every account has an email.
     */
    public function __invoke(Request $request, Telegram $telegram): RedirectResponse
    {
        $data = $request->query();

        if (! $telegram->isValidLogin($data)) {
            return redirect()->route('login')->with('status', __('Telegram login failed. Please try again.'));
        }

        $user = User::query()->where('telegram_chat_id', (int) $data['id'])->first();

        if ($user === null) {
            return redirect()->route('login')->with('status', __('This Telegram is not connected to an account. Log in with your email, then connect it on your profile page.'));
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
