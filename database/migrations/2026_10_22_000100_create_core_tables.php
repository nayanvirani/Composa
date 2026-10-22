<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Core data model: shops, plans and Shopify subscriptions, configurators with immutable
 * published versions, assignments, signed configurations, configured orders, uploads and
 * analytics. Configurator definitions and shopper answers are JSONB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('handle')->unique()->comment('Shopify App Pricing plan handle');
            $table->string('name');
            $table->string('tagline')->nullable();
            $table->unsignedInteger('price_cents')->default(0)->comment('Synced from Shopify, read-only in admin');
            $table->unsignedSmallInteger('trial_days')->default(0)->comment('Synced from Shopify');
            $table->boolean('dev_only')->default(false);
            $table->boolean('is_public')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->jsonb('limits');
            $table->jsonb('features');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('owner_name')->nullable();
            $table->text('access_token')->nullable();
            $table->string('scopes')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->string('money_format')->nullable();
            $table->string('timezone')->nullable();
            $table->string('primary_locale', 12)->default('en');
            $table->jsonb('locales')->nullable();
            $table->string('shopify_plan')->nullable()->comment('basic, shopify, advanced, plus, partner_test…');
            $table->boolean('is_plus')->default(false);
            $table->boolean('is_dev')->default(false);
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subscription_status')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->string('cart_mode', 16)->default('expand')->comment('update (Plus/dev), expand, discount');
            $table->text('signing_secret')->nullable();
            $table->string('cart_transform_id')->nullable();
            $table->string('discount_id')->nullable();
            $table->string('status', 16)->default('active')->comment('active, uninstalled, frozen');
            $table->jsonb('onboarding')->nullable();
            $table->jsonb('settings')->nullable();
            $table->jsonb('limit_overrides')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('uninstalled_at')->nullable();
            $table->timestamp('purge_after')->nullable();
            $table->timestamps();
            $table->index(['status', 'plan_id']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('shopify_id')->unique();
            $table->string('plan_handle')->nullable();
            $table->string('name')->nullable();
            $table->string('status', 32);
            $table->unsignedInteger('price_cents')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->jsonb('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('configurators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('handle');
            $table->string('template_key')->nullable();
            $table->string('status', 16)->default('draft')->comment('draft, published, archived');
            $table->string('display_mode', 16)->default('popup')->comment('popup, page');
            $table->string('variant_mode', 16)->default('variants')->comment('variants, mixed, base');
            $table->string('base_product_id')->nullable();
            $table->string('base_variant_id')->nullable();
            $table->string('base_product_title')->nullable();
            $table->string('base_product_image', 1024)->nullable();
            $table->unsignedBigInteger('published_version_id')->nullable();
            $table->unsignedBigInteger('draft_version_id')->nullable();
            $table->jsonb('display')->nullable()->comment('Button label, open mode, step UI and cart behaviour');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['shop_id', 'handle']);
            $table->index(['shop_id', 'status']);
        });

        Schema::create('configurator_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('configurator_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 16)->default('draft')->comment('draft, published, archived');
            $table->jsonb('definition');
            $table->string('notes')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['configurator_id', 'version']);
        });

        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('configurator_id')->constrained()->cascadeOnDelete();
            $table->string('target_type', 16)->comment('product, collection, tag, vendor');
            $table->string('target_id');
            $table->string('target_label')->nullable();
            $table->unsignedInteger('product_count')->default(0);
            $table->unsignedSmallInteger('priority')->default(100);
            $table->timestamps();
            $table->unique(['configurator_id', 'target_type', 'target_id']);
            $table->index(['shop_id', 'target_type', 'target_id']);
        });

        Schema::create('configurations', function (Blueprint $table) {
            $table->string('id', 40)->primary()->comment('cfg_… — the _config_id line property');
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('configurator_id')->constrained()->cascadeOnDelete();
            $table->foreignId('version_id')->constrained('configurator_versions')->cascadeOnDelete();
            $table->jsonb('answers');
            $table->jsonb('result');
            $table->jsonb('summary')->nullable()->comment('Visible line properties');
            $table->string('variant_id')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('base_price_cents');
            $table->unsignedBigInteger('price_cents');
            $table->string('currency', 3);
            $table->string('signature', 128);
            $table->string('image_url', 1024)->nullable();
            $table->string('status', 16)->default('cart')->comment('cart, ordered, expired');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['shop_id', 'status', 'created_at']);
        });

        Schema::create('configured_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('configuration_id', 40)->nullable();
            $table->foreign('configuration_id')->references('id')->on('configurations')->nullOnDelete();
            $table->foreignId('configurator_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_id');
            $table->string('order_name')->nullable();
            $table->string('line_item_id');
            $table->string('customer_name')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('line_total_cents')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('price_check', 16)->default('ok')->comment('ok, mismatch, unsigned');
            $table->string('production_status', 24)->default('new')->comment('new, in_production, ready, shipped');
            $table->string('spec_pdf_path')->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamps();
            $table->unique(['shop_id', 'order_id', 'line_item_id']);
        });

        Schema::create('uploads', function (Blueprint $table) {
            $table->string('id', 40)->primary();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('configuration_id', 40)->nullable();
            $table->string('field_key')->nullable();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime', 128)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('status', 16)->default('pending')->comment('pending, uploaded, attached, ordered, deleted');
            $table->timestamps();
            $table->index(['shop_id', 'status', 'created_at']);
        });

        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('configurator_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('session_id', 64)->nullable();
            $table->string('type', 32)->comment('open, step, add_to_cart, order, load_ms');
            $table->string('step_key')->nullable();
            $table->bigInteger('value')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamp('occurred_at');
            $table->index(['shop_id', 'configurator_id', 'occurred_at']);
        });

        Schema::create('analytics_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('configurator_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('opens')->default(0);
            $table->unsignedInteger('add_to_carts')->default(0);
            $table->unsignedInteger('orders')->default(0);
            $table->unsignedBigInteger('revenue_cents')->default(0);
            $table->jsonb('steps')->nullable()->comment('Sessions reaching each step');
            $table->jsonb('choices')->nullable()->comment('Counts per field and option');
            $table->unsignedInteger('load_ms_p75')->nullable();
            $table->unique(['configurator_id', 'date']);
        });
    }

    public function down(): void
    {
        foreach (['analytics_daily', 'analytics_events', 'uploads', 'configured_orders', 'configurations', 'assignments', 'configurator_versions', 'configurators', 'subscriptions', 'shops', 'plans'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
