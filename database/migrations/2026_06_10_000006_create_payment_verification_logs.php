<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_verification_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payment_proof_path')->nullable();
            $table->string('expected_order_number')->nullable();
            $table->string('detected_order_number')->nullable();
            $table->unsignedInteger('expected_amount_cents')->default(0);
            $table->unsignedInteger('detected_amount_cents')->nullable();
            $table->timestamp('detected_paid_at')->nullable();
            $table->string('auto_result')->default('pending')->index();
            $table->string('manual_result')->nullable()->index();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_verification_logs');
    }
};
