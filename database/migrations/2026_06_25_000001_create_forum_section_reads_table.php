<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('forum_section_reads')) {
            return;
        }

        Schema::create('forum_section_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('forum_section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->index();
            $table->timestamps();

            $table->unique(['forum_section_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_section_reads');
    }
};
