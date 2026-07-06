<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_reward_rules', function (Blueprint $table): void {
            if (! Schema::hasColumn('referral_reward_rules', 'trigger_events')) {
                $table->json('trigger_events')->nullable()->after('name');
            }

            if (! Schema::hasColumn('referral_reward_rules', 'product_ids')) {
                $table->json('product_ids')->nullable()->after('trigger_events');
            }
        });

        Schema::create('event_reward_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('referral_reward_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->timestamps();

            $table->unique(
                ['referral_reward_rule_id', 'user_id', 'event', 'subject_type', 'subject_id'],
                'event_reward_grants_unique_subject'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_reward_grants');

        Schema::table('referral_reward_rules', function (Blueprint $table): void {
            if (Schema::hasColumn('referral_reward_rules', 'product_ids')) {
                $table->dropColumn('product_ids');
            }

            if (Schema::hasColumn('referral_reward_rules', 'trigger_events')) {
                $table->dropColumn('trigger_events');
            }
        });
    }
};
