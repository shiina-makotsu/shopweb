<?php

namespace App\Services;

use App\Models\FlashSale;
use App\Models\FlashSaleCampaign;
use App\Models\FlashSaleCampaignItem;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class FlashSaleCampaignService
{
    public function syncCampaign(FlashSaleCampaign $campaign, ?CarbonInterface $from = null, ?CarbonInterface $until = null): int
    {
        $from = CarbonImmutable::parse($from ?: now())->startOfDay();
        $until = CarbonImmutable::parse($until ?: $from->addDays(max(1, (int) $campaign->generate_days_ahead)))->endOfDay();
        $created = 0;

        if (! $campaign->is_active) {
            return 0;
        }

        $campaign->loadMissing(['items' => fn ($query) => $query->where('is_active', true)]);

        foreach ($this->occurrences($campaign, $from, $until) as $startsAt) {
            $endsAt = $this->endsAt($campaign, $startsAt);

            foreach ($campaign->items as $item) {
                if ($this->syncItemOccurrence($campaign, $item, $startsAt, $endsAt)) {
                    $created++;
                }
            }
        }

        $campaign->forceFill(['last_generated_at' => now()])->save();

        return $created;
    }

    public function syncDueCampaigns(): int
    {
        return FlashSaleCampaign::query()
            ->where('is_active', true)
            ->with('items')
            ->get()
            ->sum(fn (FlashSaleCampaign $campaign): int => $this->syncCampaign($campaign));
    }

    /**
     * @return array<int, CarbonImmutable>
     */
    private function occurrences(FlashSaleCampaign $campaign, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $windowStart = $campaign->starts_on ? CarbonImmutable::parse($campaign->starts_on)->startOfDay() : $from;
        $windowEnd = $campaign->ends_on ? CarbonImmutable::parse($campaign->ends_on)->endOfDay() : $until;
        $from = $from->max($windowStart);
        $until = $until->min($windowEnd);

        if ($from->gt($until)) {
            return [];
        }

        $days = [];
        for ($day = $from; $day->lte($until); $day = $day->addDay()) {
            if ($this->matchesSchedule($campaign, $day)) {
                $days[] = $day->setTimeFromTimeString($this->startTime($campaign));
            }
        }

        return $days;
    }

    private function matchesSchedule(FlashSaleCampaign $campaign, CarbonImmutable $day): bool
    {
        return match ($campaign->schedule_type) {
            FlashSaleCampaign::TYPE_DAILY => true,
            FlashSaleCampaign::TYPE_WEEKLY => in_array($day->dayOfWeekIso, $this->integerList($campaign->week_days), true),
            FlashSaleCampaign::TYPE_MONTHLY => in_array($day->day, $this->integerList($campaign->month_days), true),
            FlashSaleCampaign::TYPE_YEARLY => in_array($day->format('m-d'), $this->stringList($campaign->year_dates), true),
            default => $campaign->starts_on && $day->isSameDay($campaign->starts_on),
        };
    }

    private function syncItemOccurrence(
        FlashSaleCampaign $campaign,
        FlashSaleCampaignItem $item,
        CarbonImmutable $startsAt,
        ?CarbonImmutable $endsAt,
    ): bool {
        $flashSale = FlashSale::query()
            ->where('flash_sale_campaign_item_id', $item->id)
            ->where('starts_at', $startsAt)
            ->first();

        $attributes = [
            'flash_sale_campaign_id' => $campaign->id,
            'flash_sale_campaign_item_id' => $item->id,
            'product_id' => $item->product_id,
            'product_variant_ids' => $item->product_variant_ids,
            'name' => $campaign->name,
            'sale_price_cents' => $item->sale_price_cents,
            'quantity_limit' => $item->quantity_limit,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'is_active' => $campaign->is_active && $item->is_active,
        ];

        if ($flashSale) {
            $flashSale->fill($attributes)->save();

            return false;
        }

        FlashSale::query()->create($attributes + ['sold_quantity' => 0]);

        return true;
    }

    private function endsAt(FlashSaleCampaign $campaign, CarbonImmutable $startsAt): ?CarbonImmutable
    {
        if (! $campaign->ends_at_time) {
            return null;
        }

        $endsAt = $startsAt->setTimeFromTimeString((string) $campaign->ends_at_time);

        return $endsAt->lte($startsAt) ? $endsAt->addDay() : $endsAt;
    }

    private function startTime(FlashSaleCampaign $campaign): string
    {
        return (string) ($campaign->starts_at_time ?: '00:00:00');
    }

    /**
     * @param  mixed  $values
     * @return array<int, int>
     */
    private function integerList(mixed $values): array
    {
        return collect(is_array($values) ? $values : [])
            ->map(fn ($value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->values()
            ->all();
    }

    /**
     * @param  mixed  $values
     * @return array<int, string>
     */
    private function stringList(mixed $values): array
    {
        return collect(is_array($values) ? $values : [])
            ->map(fn ($value): string => (string) $value)
            ->filter()
            ->values()
            ->all();
    }
}
