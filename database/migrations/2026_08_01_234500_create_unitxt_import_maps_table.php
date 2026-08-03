<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unitxt_import_maps', function (Blueprint $table) {
            $table->id();
            $table->string('source')->default('unitxt')->index();
            $table->unsignedBigInteger('old_user_id')->index();
            $table->unsignedBigInteger('old_customer_id')->nullable();
            $table->string('old_email')->index();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sender_ids_imported')->default(0);
            $table->unsignedInteger('contacts_imported')->default(0);
            $table->decimal('balance_imported', 14, 2)->default(0);
            $table->json('meta')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['source', 'old_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unitxt_import_maps');
    }
};
