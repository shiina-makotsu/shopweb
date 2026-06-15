<?php

namespace App\Console\Commands;

use App\Services\AiUsageService;
use Illuminate\Console\Command;

class PruneAiTrashCommand extends Command
{
    protected $signature = 'shop:ai-trash-prune';

    protected $description = 'Delete expired AI image tasks and chat sessions from the recycle bin.';

    public function handle(AiUsageService $usage): int
    {
        $result = $usage->purgeExpiredAiTrash();

        $this->info(sprintf(
            'Deleted %d AI image tasks and %d AI chat sessions older than %s.',
            $result['image_tasks'],
            $result['chat_sessions'],
            $result['expired_before'],
        ));

        return self::SUCCESS;
    }
}
