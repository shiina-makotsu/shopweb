<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('after_sales_requests')) {
            return;
        }

        if (! Schema::hasColumn('after_sales_requests', 'refund_status')) {
            Schema::table('after_sales_requests', function (Blueprint $table): void {
                $table->string('refund_status')->nullable()->index();
            });
        }

        if (! Schema::hasColumn('after_sales_requests', 'refund_requested_by_id')) {
            Schema::table('after_sales_requests', function (Blueprint $table): void {
                $table->foreignId('refund_requested_by_id')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('after_sales_requests', 'refund_requested_at')) {
            Schema::table('after_sales_requests', function (Blueprint $table): void {
                $table->timestamp('refund_requested_at')->nullable();
            });
        }

        if (! Schema::hasColumn('after_sales_requests', 'refund_reviewed_by_id')) {
            Schema::table('after_sales_requests', function (Blueprint $table): void {
                $table->foreignId('refund_reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('after_sales_requests', 'refund_reviewed_at')) {
            Schema::table('after_sales_requests', function (Blueprint $table): void {
                $table->timestamp('refund_reviewed_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Repair migrations do not remove canonical columns from existing installations.
    }
};
