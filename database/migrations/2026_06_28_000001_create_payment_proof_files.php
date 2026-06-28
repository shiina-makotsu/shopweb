<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_proof_files')) {
            return;
        }

        Schema::create('payment_proof_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('disk_path')->nullable()->index();
            $table->string('original_name')->nullable();
            $table->string('mime_type', 120)->default('application/octet-stream');
            $table->unsignedBigInteger('size')->default(0);
            $table->longText('content');
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_proof_files');
    }
};
