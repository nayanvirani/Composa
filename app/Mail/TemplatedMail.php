<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * An email whose subject and body come from the email_templates table (edited in super admin).
 * Tokens like {{shop_name}} are replaced with escaped values; the body is Markdown.
 */
class TemplatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $templateKey, public array $tokens = []) {}

    public static function exists(string $key): bool
    {
        return EmailTemplate::query()->where('key', $key)->where('active', true)->exists();
    }

    public static function fill(string $text, array $tokens, bool $escape): string
    {
        return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', function ($m) use ($tokens, $escape) {
            $value = (string) ($tokens[$m[1]] ?? '');

            return $escape ? e($value) : $value;
        }, $text);
    }

    private function template(): EmailTemplate
    {
        return EmailTemplate::query()->where('key', $this->templateKey)->where('active', true)->firstOrFail();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: self::fill($this->template()->subject, $this->tokens, false));
    }

    public function content(): Content
    {
        return new Content(htmlString: view('mail.templated', [
            'html' => Str::markdown(self::fill($this->template()->body, $this->tokens, true), ['html_input' => 'strip', 'allow_unsafe_links' => false]),
        ])->render());
    }
}
