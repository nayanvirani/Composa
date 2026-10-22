<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Mail\TemplatedMail;
use App\Models\BetaApplication;
use App\Models\ContactSubmission;
use App\Models\NewsletterSubscriber;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/** Website forms. Submissions land in super admin (Support); a hidden field filters bots. */
class FormController extends Controller
{
    private function guard(Request $request, string $form): ?RedirectResponse
    {
        if (filled($request->input('website'))) {
            return back()->with('form_sent', $form); // honeypot: pretend it worked
        }
        if (RateLimiter::tooManyAttempts('form:'.$form.':'.$request->ip(), 5)) {
            return back()->withErrors(['form' => 'Too many submissions. Please try again in a few minutes.'])->withInput();
        }
        RateLimiter::hit('form:'.$form.':'.$request->ip(), 600);

        return null;
    }

    public function contact(Request $request): RedirectResponse
    {
        if ($r = $this->guard($request, 'contact')) {
            return $r;
        }
        $data = $request->validate([
            'topic' => 'required|in:support,sales,partners,press,privacy',
            'name' => 'required|string|max:120',
            'email' => 'required|email:rfc|max:190',
            'store' => 'nullable|string|max:190',
            'extra' => 'nullable|string|max:190',
            'message' => 'required|string|min:10|max:5000',
        ]);
        ContactSubmission::query()->create($data + ['status' => 'new']);
        if ($to = SiteSetting::get('contact.support_email')) {
            if (TemplatedMail::exists('contact_received')) {
                Mail::to($to)->queue(new TemplatedMail('contact_received', $data));
            }
        }

        return back()->with('form_sent', 'contact');
    }

    public function beta(Request $request): RedirectResponse
    {
        if ($r = $this->guard($request, 'beta')) {
            return $r;
        }
        $data = $request->validate([
            'store' => 'required|string|max:190',
            'email' => 'required|email:rfc|max:190',
            'shopify_plan' => 'nullable|string|max:40',
            'categories' => 'nullable|array|max:8',
            'categories.*' => 'string|max:40',
            'has_variants' => 'nullable|in:yes,no,some',
            'current_process' => 'nullable|string|max:2000',
            'must_have' => 'nullable|string|max:2000',
        ]);
        BetaApplication::query()->create($data + ['status' => 'new']);

        return back()->with('form_sent', 'beta');
    }

    public function newsletter(Request $request): RedirectResponse
    {
        if ($r = $this->guard($request, 'newsletter')) {
            return $r;
        }
        $data = $request->validate(['email' => 'required|email:rfc|max:190', 'source' => 'nullable|string|max:32']);
        NewsletterSubscriber::query()->firstOrCreate(['email' => mb_strtolower($data['email'])], ['source' => $data['source'] ?? 'footer']);

        return back()->with('form_sent', 'newsletter');
    }
}
