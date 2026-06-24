<?php

use App\Filament\Pages\GuideAiSettingsPage;
use App\Filament\Pages\SupportAiSettingsPage;
use App\Filament\Pages\UserAiPage;
use App\Filament\Resources\AiWorkflowResource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_workflows')) {
            Schema::create('ai_workflows', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('type')->default('chat')->index();
                $table->string('trigger_key')->nullable()->index();
                $table->text('description')->nullable();
                $table->json('nodes')->nullable();
                $table->json('edges')->nullable();
                $table->string('entry_node_id')->nullable();
                $table->string('output_node_id')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        Schema::table('site_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('site_settings', 'guide_ai_workflow_slug')) {
                $table->string('guide_ai_workflow_slug')->nullable()->after('guide_pet_context_mode');
            }

            if (! Schema::hasColumn('site_settings', 'support_ai_workflow_slug')) {
                $table->string('support_ai_workflow_slug')->nullable()->after('support_ai_idle_minutes');
            }
        });

        $this->moveAiMenuItems();
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_workflows');

        Schema::table('site_settings', function (Blueprint $table): void {
            foreach (['guide_ai_workflow_slug', 'support_ai_workflow_slug'] as $column) {
                if (Schema::hasColumn('site_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function moveAiMenuItems(): void
    {
        if (! Schema::hasTable('admin_menu_items')) {
            return;
        }

        $aiGroupId = DB::table('admin_menu_items')->where('item_key', 'group:AI')->value('id');

        if (! $aiGroupId) {
            $aiGroupId = DB::table('admin_menu_items')->insertGetId([
                'item_key' => 'group:AI',
                'type' => 'group',
                'label' => 'AI',
                'sort_order' => 65,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('admin_menu_items')
            ->whereIn('source_class', [
                SupportAiSettingsPage::class,
                UserAiPage::class,
                GuideAiSettingsPage::class,
                AiWorkflowResource::class,
            ])
            ->update([
                'parent_id' => $aiGroupId,
                'updated_at' => now(),
            ]);
    }
};
