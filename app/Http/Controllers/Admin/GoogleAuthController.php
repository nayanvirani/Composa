<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

/**
 * Staff sign-in with Google. Only existing, active staff accounts with a role can sign in; the
 * Google account is matched by email and linked on first use. New staff are invited from Team.
 */
class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        abort_unless(config('composa.admin.google_sso'), 404);

        $driver = Socialite::driver('google')->scopes(['openid', 'email', 'profile']);
        if ($domains = config('composa.admin.allowed_email_domains')) {
            $driver->with(['hd' => count($domains) === 1 ? $domains[0] : '*']);
        }

        return $driver->redirect();
    }

    public function callback(): RedirectResponse
    {
        abort_unless(config('composa.admin.google_sso'), 404);
        $login = filament()->getPanel('admin')->getLoginUrl();

        try {
            $google = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            return redirect($login)->with('google_error', 'Google sign-in failed. Please try again.');
        }

        $email = mb_strtolower((string) $google->getEmail());
        $domains = config('composa.admin.allowed_email_domains');
        $verified = (bool) ($google->user['email_verified'] ?? $google->user['verified_email'] ?? false);
        if (! $email || ! $verified || ($domains && ! in_array(substr(strrchr($email, '@'), 1), $domains, true))) {
            return redirect($login)->with('google_error', 'This Google account is not allowed.');
        }

        $user = User::query()->where('google_id', $google->getId())->orWhere('email', $email)->first();
        if (! $user || ! $user->active || ! $user->roles()->exists()) {
            return redirect($login)->with('google_error', 'No active staff account for '.$email.'. Ask an Owner to invite you.');
        }

        $user->forceFill(['google_id' => $google->getId(), 'avatar_url' => $google->getAvatar(), 'last_login_at' => now()])->save();
        Auth::login($user, remember: true);
        session()->regenerate();
        Audit::log('team.signed_in', 'Google sign-in', $user);

        return redirect()->intended(filament()->getPanel('admin')->getUrl());
    }
}
