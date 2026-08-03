<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('queued_at')->index();
            $table->string('otp_reference')->nullable()->after('uuid')->index();
        });

        Schema::create('otp_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->string('phone_normalized')->index();
            $table->string('code_hash');
            $table->string('purpose')->nullable();
            $table->string('status')->default('pending')->index();
            $table->string('request_ip', 45)->nullable();
            $table->string('device_id')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'phone_normalized', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_challenges');

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['expires_at', 'otp_reference']);
        });
    }
};
