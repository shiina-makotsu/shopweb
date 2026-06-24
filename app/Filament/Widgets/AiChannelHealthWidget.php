<?php

namespace App\Filament\Widgets;

use App\Services\AiChannelHealthService;
use Filament\Widgets\Widget;

class AiChannelHealthWidget extends Widget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '30s';

    protected string $view = 'filament.widgets.ai-channel-health';

    protected int | string | array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'channels' => app(AiChannelHealthService::class)->status(),
        ];
    }
}
