<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'birthday')) {
                $table->date('birthday')->nullable()->after('profile_intro');
            }

            if (! Schema::hasColumn('users', 'has_diagnosis_certificate')) {
                $table->boolean('has_diagnosis_certificate')->default(false)->after('birthday');
            }
        });

        if (! Schema::hasTable('user_profile_change_logs')) {
            Schema::create('user_profile_change_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('changed_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('field', 80);
                $table->text('old_value')->nullable();
                $table->text('new_value')->nullable();
                $table->string('source', 40)->default('user');
                $table->timestamps();

                $table->index(['user_id', 'field']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profile_change_logs');

        Schema::table('users', function (Blueprint $table): void {
            foreach ([
                'has_diagnosis_certificate',
                'birthday',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
