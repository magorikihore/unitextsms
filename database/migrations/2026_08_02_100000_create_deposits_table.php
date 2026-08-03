<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('uuid')->unique();
            $table->string('reference')->unique();
            $table->string('request_ref')->nullable()->unique();
            $table->string('operator_ref')->nullable();
            $table->string('receipt_number')->nullable();
            $table->string('phone', 20);
            $table->string('operator')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 8)->default('TZS');
            $table->decimal('credits', 14, 2)->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->string('failure_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};
