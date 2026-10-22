<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Website CMS managed from super admin: pages built from section blocks, journal posts, docs,
 * FAQs, changelog, menus, site settings, announcements (website and in-app), email templates,
 * testimonials, status page, media and form submissions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->string('locale', 12)->default('en');
            $table->string('title');
            $table->string('template', 32)->default('sections')->comment('sections, legal');
            $table->jsonb('sections')->nullable()->comment('Ordered blocks: {type, visible, data}');
            $table->jsonb('seo')->nullable();
            $table->string('status', 16)->default('draft')->comment('draft, published');
            $table->timestamp('publish_at')->nullable();
            $table->timestamps();
            $table->unique(['slug', 'locale']);
        });

        Schema::create('page_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->jsonb('snapshot');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('excerpt', 500)->nullable();
            $table->longText('body');
            $table->string('tag', 32)->nullable();
            $table->string('author')->default('Composa team');
            $table->string('cover_image', 1024)->nullable();
            $table->unsignedSmallInteger('read_minutes')->default(5);
            $table->boolean('featured')->default(false);
            $table->jsonb('seo')->nullable();
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('doc_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('doc_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doc_category_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('title');
            $table->longText('body');
            $table->string('plan_note')->nullable();
            $table->unsignedSmallInteger('read_minutes')->default(5);
            $table->boolean('start_here')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->string('status', 16)->default('draft');
            $table->unsignedInteger('helpful_yes')->default(0);
            $table->unsignedInteger('helpful_no')->default(0);
            $table->timestamps();
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->text('answer');
            $table->jsonb('placements')->comment('home, pricing, help, app');
            $table->string('locale', 12)->default('en');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->string('status', 16)->default('published');
            $table->timestamps();
        });

        Schema::create('changelog_entries', function (Blueprint $table) {
            $table->id();
            $table->string('version', 32);
            $table->string('title');
            $table->jsonb('items')->comment('[{kind: added|changed|fixed, text}]');
            $table->date('released_at');
            $table->string('status', 16)->default('published');
            $table->timestamps();
        });

        Schema::create('navigation_menus', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->comment('header, footer_product, footer_resources…');
            $table->string('title');
            $table->jsonb('items')->comment('[{label, url}]');
            $table->timestamps();
        });

        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->jsonb('value')->nullable();
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 16)->comment('site, app');
            $table->string('message');
            $table->string('link_label')->nullable();
            $table->string('link_url')->nullable();
            $table->jsonb('audience')->nullable()->comment('App: plan handles; empty = everyone');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('subject');
            $table->text('body');
            $table->string('description')->nullable();
            $table->jsonb('tokens')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->text('quote');
            $table->string('name');
            $table->string('store')->nullable();
            $table->string('status', 16)->default('draft');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('status_components', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('status', 16)->default('operational')->comment('operational, degraded, outage');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('status_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('severity', 16)->default('minor');
            $table->jsonb('component_ids')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->string('disk', 32)->default('public');
            $table->string('path');
            $table->string('alt')->nullable();
            $table->string('mime', 128)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });

        Schema::create('contact_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('topic', 32);
            $table->string('name');
            $table->string('email');
            $table->string('store')->nullable();
            $table->string('extra')->nullable();
            $table->text('message');
            $table->string('status', 16)->default('new');
            $table->string('ticket_ref', 32)->nullable();
            $table->timestamps();
        });

        Schema::create('beta_applications', function (Blueprint $table) {
            $table->id();
            $table->string('store');
            $table->string('email');
            $table->string('shopify_plan')->nullable();
            $table->jsonb('categories')->nullable();
            $table->string('has_variants', 16)->nullable();
            $table->text('current_process')->nullable();
            $table->text('must_have')->nullable();
            $table->string('status', 16)->default('new')->comment('new, contacted, accepted, declined');
            $table->timestamps();
        });

        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('source', 32)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['newsletter_subscribers', 'beta_applications', 'contact_submissions', 'media_assets', 'status_incidents', 'status_components', 'testimonials', 'email_templates', 'announcements', 'site_settings', 'navigation_menus', 'changelog_entries', 'faqs', 'doc_articles', 'doc_categories', 'posts', 'page_revisions', 'pages'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
