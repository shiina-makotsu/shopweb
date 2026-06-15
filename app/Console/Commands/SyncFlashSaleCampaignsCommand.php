<?php

namespace App\Console\Commands;

use App\Services\FlashSaleCampaignService;
use Illuminate\Console\Command;

class SyncFlashSaleCampaignsCommand extends Command
{
    protected $signature = 'shop:flash-sale-sync';

    protected $description = 'Generate upcoming flash-sale sessions from active flash-sale campaigns.';

    public function handle(FlashSaleCampaignService $service): int
    {
        $created = $service->syncDueCampaigns();

        $this->info(sprintf('Synced flash-sale campaigns: %d new sessions.', $created));

        return self::SUCCESS;
    }
}
