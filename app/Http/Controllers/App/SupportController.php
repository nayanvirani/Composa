<?php

namespace App\Http\Controllers\App;

use App\Models\DocArticle;
use App\Models\Faq;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportController extends Controller
{
    public function index(): Response
    {
        $shop = $this->shop();

        return Inertia::render('Support', [
            'faqs' => Faq::query()->where('status', 'published')->whereJsonContains('placements', 'app')->orderBy('sort')->get(['id', 'question', 'answer']),
            'articles' => DocArticle::query()->where('status', 'published')->where('start_here', true)->orderBy('sort')->take(6)->get(['slug', 'title', 'read_minutes']),
            'tickets' => $shop->tickets()->latest()->take(10)->get(['id', 'subject', 'status', 'created_at']),
            'supportLevel' => $shop->plan?->feature('support', 'Docs'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['subject' => 'required|string|max:200', 'message' => 'required|string|max:5000', 'email' => 'nullable|email', 'tag' => 'nullable|string|max:32']);
        $shop = $this->shop();
        $priority = in_array($shop->plan?->handle, ['growth', 'pro'], true) ? 'high' : 'normal';
        $ticket = SupportTicket::query()->create([
            'shop_id' => $shop->id, 'subject' => $data['subject'], 'requester_email' => $data['email'] ?? $shop->email,
            'requester_name' => $shop->owner_name, 'status' => 'open', 'priority' => $priority, 'tag' => $data['tag'] ?? null, 'source' => 'app', 'last_reply_at' => now(),
        ]);
        $ticket->messages()->create(['author_type' => 'merchant', 'body' => $data['message']]);

        return response()->json(['id' => $ticket->id]);
    }
}
