<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('driver')->default('jasmin');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('rate_limit_per_second')->default(18);
            $table->decimal('balance', 14, 2)->nullable();
            $table->decimal('cost_per_sms', 10, 4)->default(0);
            $table->text('credentials')->nullable();
            $table->string('webhook_url')->nullable();
            $table->unsignedInteger('avg_latency_ms')->nullable();
            $table->timestamp('last_health_check_at')->nullable();
            $table->string('health_status')->default('unknown');
            $table->timestamps();
        });

        Schema::create('sms_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sms_provider_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category');
            $table->string('country_code', 8)->default('TZ');
            $table->string('network')->nullable();
            $table->unsignedTinyInteger('priority')->default(1);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_failover')->default(false);
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('company_name');
            $table->string('trading_name')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('contact_name');
            $table->string('contact_email');
            $table->string('contact_phone')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country', 8)->default('TZ');
            $table->string('status')->default('pending')->index();
            $table->string('billing_type')->default('prepaid');
            $table->decimal('sms_balance', 14, 2)->default(0);
            $table->decimal('credit_limit', 14, 2)->default(0);
            $table->decimal('price_per_sms', 10, 4)->default(0.05);
            $table->decimal('low_balance_threshold', 14, 2)->default(100);
            $table->json('allowed_countries')->nullable();
            $table->json('allowed_networks')->nullable();
            $table->boolean('can_send_otp')->default(true);
            $table->boolean('can_send_transactional')->default(true);
            $table->boolean('can_send_marketing')->default(false);
            $table->boolean('api_enabled')->default(false);
            $table->unsignedInteger('daily_send_limit')->nullable();
            $table->unsignedInteger('monthly_send_limit')->nullable();
            $table->foreignId('default_provider_id')->nullable()->constrained('sms_providers')->nullOnDelete();
            $table->json('kyc_documents')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('client_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('report_viewer');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['client_id', 'user_id']);
        });

        Schema::create('credit_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_before', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->string('reference')->unique();
            $table->string('reason');
            $table->nullableMorphs('related');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'created_at']);
        });

        Schema::create('sender_ids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('sender');
            $table->string('status')->default('pending')->index();
            $table->string('category')->nullable();
            $table->string('provider_status')->nullable();
            $table->string('regulator_status')->nullable();
            $table->text('supporting_documents')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['client_id', 'sender']);
        });

        Schema::create('contact_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('phone');
            $table->string('phone_normalized')->index();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->json('custom_fields')->nullable();
            $table->json('tags')->nullable();
            $table->string('consent_status')->default('unknown');
            $table->boolean('is_opted_out')->default(false);
            $table->boolean('is_valid')->default(true);
            $table->timestamps();

            $table->unique(['client_id', 'phone_normalized']);
        });

        Schema::create('contact_contact_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_group_id')->constrained()->cascadeOnDelete();
            $table->unique(['contact_id', 'contact_group_id'], 'contact_group_unique');
        });

        Schema::create('suppressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('phone_normalized')->index();
            $table->string('reason')->nullable();
            $table->boolean('is_global')->default(false);
            $table->timestamps();

            $table->unique(['client_id', 'phone_normalized']);
        });

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('filename');
            $table->string('type')->default('contacts');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported')->default(0);
            $table->unsignedInteger('duplicates')->default(0);
            $table->unsignedInteger('invalid')->default(0);
            $table->string('status')->default('completed');
            $table->json('summary')->nullable();
            $table->timestamps();
        });

        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category');
            $table->string('language')->default('en');
            $table->text('body');
            $table->json('variables')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sender_id_id')->nullable()->constrained('sender_ids')->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('templates')->nullOnDelete();
            $table->string('name');
            $table->string('status')->default('draft')->index();
            $table->string('category')->default('marketing');
            $table->text('message_body');
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('valid_count')->default(0);
            $table->unsignedInteger('invalid_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('opted_out_count')->default(0);
            $table->unsignedInteger('queued_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('estimated_units')->default(0);
            $table->decimal('estimated_cost', 14, 2)->default(0);
            $table->decimal('charged_amount', 14, 2)->default(0);
            $table->string('content_hash')->nullable()->index();
            $table->json('source_meta')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sender_id_id')->nullable()->constrained('sender_ids')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sms_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('to_phone');
            $table->string('to_phone_normalized')->index();
            $table->string('from_sender');
            $table->text('body');
            $table->string('category')->index();
            $table->string('status')->default('queued')->index();
            $table->string('queue_name')->index();
            $table->unsignedTinyInteger('units')->default(1);
            $table->decimal('client_charge', 10, 4)->default(0);
            $table->decimal('provider_cost', 10, 4)->default(0);
            $table->string('provider_message_id')->nullable()->index();
            $table->string('failure_reason')->nullable();
            $table->string('network')->nullable();
            $table->string('country_code', 8)->default('TZ');
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->unsignedInteger('delivery_ms')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'created_at']);
            $table->index(['status', 'category']);
        });

        Schema::create('api_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('key_id')->unique();
            $table->string('secret_hash');
            $table->string('secret_hint', 8)->nullable();
            $table->json('ip_allowlist')->nullable();
            $table->boolean('sandbox')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('rate_limit_per_minute')->default(60);
            $table->string('webhook_url')->nullable();
            $table->string('webhook_signing_secret')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['client_id', 'created_at']);
        });

        Schema::create('system_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('severity')->default('warning');
            $table->string('title');
            $table->text('body');
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_alerts');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('api_credentials');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('templates');
        Schema::dropIfExists('import_batches');
        Schema::dropIfExists('suppressions');
        Schema::dropIfExists('contact_contact_group');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('contact_groups');
        Schema::dropIfExists('sender_ids');
        Schema::dropIfExists('credit_ledger_entries');
        Schema::dropIfExists('client_user');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('sms_routes');
        Schema::dropIfExists('sms_providers');
    }
};
