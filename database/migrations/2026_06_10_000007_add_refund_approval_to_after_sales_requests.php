<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('after_sales_requests', function (Blueprint $table): void {
            $table->string('refund_status')->nullable()->after('refund_amount_cents')->index();
            $table->foreignId('refund_requested_by_id')->nullable()->after('refund_status')->constrained('users')->nullOnDelete();
            $table->timestamp('refund_requested_at')->nullable()->after('refund_requested_by_id');
            $table->foreignId('refund_reviewed_by_id')->nullable()->after('refund_requested_at')->constrained('users')->nullOnDelete();
            $table->timestamp('refund_reviewed_at')->nullable()->after('refund_reviewed_by_id');
        });
    }

    public function down(): void
    {
        Schema::table('after_sales_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('refund_reviewed_by_id');
            $table->dropColumn('refund_reviewed_at');
            $table->dropConstrainedForeignId('refund_requested_by_id');
            $table->dropColumn('refund_requested_at');
            $table->dropColumn('refund_status');
        });
    }
};
