<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Super admin side: staff accounts (users), global templates, feature flags, monitoring events,
 * support tickets, audit log, impersonation sessions, shop notes and the GDPR queue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->string('avatar_url', 1024)->nullable()->after('google_id');
            $table->boolean('active')->default(true)->after('avatar_url');
            $table->timestamp('last_login_at')->nullable();
        });

        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('category', 32);
            $table->text('description')->nullable();
            $table->string('cover_image', 1024)->nullable();
            $table->string('min_plan')->nullable()->comment('Plan handle needed for features it uses');
            $table->boolean('featured')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->string('status', 16)->default('draft')->comment('draft, published, archived');
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->unsignedInteger('install_count')->default(0);
            $table->timestamps();
        });

        Schema::create('template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->jsonb('definition');
            $table->text('changelog')->nullable();
            $table->string('rollout', 16)->default('all')->comment('all, plans, flag');
            $table->jsonb('rollout_value')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['template_id', 'version']);
        });

        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('description')->nullable();
            $table->string('state', 16)->default('off')->comment('off, on, targeted');
            $table->jsonb('plans')->nullable();
            $table->jsonb('shop_ids')->nullable();
            $table->timestamps();
        });

        Schema::create('system_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 48)->comment('price_mismatch, signature_failed, webhook_failed, slow_load, cart_transform_conflict, job_failed…');
            $table->string('severity', 16)->default('warning')->comment('info, warning, critical');
            $table->string('fingerprint', 64);
            $table->string('message');
            $table->jsonb('context')->nullable();
            $table->unsignedInteger('count')->default(1);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('resolved_at')->nullable();
            $table->unique('fingerprint');
            $table->index(['resolved_at', 'severity']);
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->string('requester_email')->nullable();
            $table->string('requester_name')->nullable();
            $table->string('status', 16)->default('open')->comment('open, pending, closed');
            $table->string('priority', 16)->default('normal')->comment('low, normal, high, urgent');
            $table->string('tag', 32)->nullable();
            $table->string('source', 16)->default('app')->comment('app, website, email');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_reply_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'priority']);
        });

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->string('author_type', 16)->comment('merchant, admin, note');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('canned_replies', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shop_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 64);
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->string('summary')->nullable();
            $table->jsonb('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['shop_id', 'created_at']);
        });

        Schema::create('impersonations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 8)->default('read')->comment('read, write');
            $table->string('reason');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('shop_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('gdpr_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->nullable()->constrained()->nullOnDelete();
            $table->string('shop_domain');
            $table->string('topic', 32)->comment('customers/data_request, customers/redact, shop/redact');
            $table->jsonb('payload');
            $table->string('status', 16)->default('pending')->comment('pending, done, failed');
            $table->text('result')->nullable();
            $table->timestamp('due_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        foreach (['gdpr_requests', 'shop_notes', 'impersonations', 'audit_logs', 'canned_replies', 'ticket_messages', 'support_tickets', 'system_events', 'feature_flags', 'template_versions', 'templates'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google_id', 'avatar_url', 'active', 'last_login_at']);
        });
    }
};
