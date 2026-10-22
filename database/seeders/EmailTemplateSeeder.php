<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

/** Default transactional emails, editable in super admin (Website → Emails). */
class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            ['welcome', 'Welcome', 'Welcome to Composa, {{shop_name}}', "Hi {{owner_name}},\n\nThanks for installing Composa. Three steps to your first live configurator:\n\n1. Enable the app embed in your theme\n2. Pick a template and choose your base product\n3. Assign it to products and publish\n\nReply to this email if you get stuck — a person reads every message.", 'After install', ['shop_name', 'owner_name']],
            ['trial_ending', 'Trial ending', 'Your Composa trial ends in {{days}} days', "Hi {{owner_name}},\n\nYour {{plan_name}} trial ends on {{trial_ends_at}}. Billing starts automatically through Shopify — there's nothing to do if you want to continue.\n\nTo change plans, open Composa → Billing.", '3 days before a trial ends', ['owner_name', 'plan_name', 'days', 'trial_ends_at']],
            ['limit_warning', 'Limit warning (80%)', 'You’ve used {{percent}}% of your {{limit_name}}', "Hi {{owner_name}},\n\n{{shop_name}} is using {{used}} of {{limit}} {{limit_name}} on the {{plan_name}} plan. At 100%, new items of that kind pause until you upgrade.\n\nCompare plans in Composa → Billing.", 'A usage meter reaches 80%', ['owner_name', 'shop_name', 'used', 'limit', 'limit_name', 'percent', 'plan_name']],
            ['limit_reached', 'Limit reached (100%)', 'You’ve reached your {{limit_name}} limit', "Hi {{owner_name}},\n\n{{shop_name}} has reached its {{limit_name}} limit ({{limit}}) on the {{plan_name}} plan. Everything already live keeps working; new items of that kind are paused.\n\nUpgrade in Composa → Billing to continue.", 'A usage meter reaches 100%', ['owner_name', 'shop_name', 'limit', 'limit_name', 'plan_name']],
            ['downgrade_grace', 'Downgrade grace period', 'Action needed: {{count}} configurators above your new plan', "Hi {{owner_name}},\n\nYour new {{plan_name}} plan allows {{limit}} published configurators and you have {{count}}. On {{grace_ends_at}}, the most recently published ones will return to draft.\n\nUnpublish the ones you don't need, or keep your current plan.", 'After a downgrade', ['owner_name', 'plan_name', 'limit', 'count', 'grace_ends_at']],
            ['ticket_reply', 'Support reply', 'Re: {{subject}} [{{ticket}}]', "Hi {{name}},\n\n{{reply}}\n\n— {{agent}}, Composa support", 'Staff reply to a ticket', ['name', 'subject', 'reply', 'agent', 'ticket']],
            ['contact_received', 'Contact form (to staff)', 'New contact: {{topic}} from {{name}}', "**{{name}}** ({{email}}) · {{store}}\n\nTopic: {{topic}}\n\n{{message}}", 'Website contact form, sent to the support address', ['name', 'email', 'store', 'topic', 'message']],
            ['uninstalled', 'Uninstall', 'Sorry to see you go', "Hi {{owner_name}},\n\nComposa has been removed from {{shop_name}}. Your data will be deleted in 48 hours; reinstall before then to keep your configurators.\n\nIf something didn't work, we'd like to hear about it — just reply.", 'After uninstall', ['owner_name', 'shop_name']],
        ];
        foreach ($templates as [$key, $name, $subject, $body, $description, $tokens]) {
            EmailTemplate::query()->firstOrCreate(['key' => $key], compact('name', 'subject', 'body', 'description', 'tokens') + ['active' => true]);
        }
    }
}
