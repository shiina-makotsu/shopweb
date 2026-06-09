<?php

namespace App\Filament\Resources\SupportTicketResource\Pages;

use App\Filament\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use Filament\Resources\Pages\EditRecord;

class EditSupportTicket extends EditRecord
{
    protected static string $resource = SupportTicketResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['status'] ?? null) === SupportTicket::STATUS_CLOSED) {
            $data['closed_at'] = now();
        }

        if (($data['admin_reply'] ?? null) && ($data['status'] ?? null) === SupportTicket::STATUS_OPEN) {
            $data['status'] = SupportTicket::STATUS_REPLIED;
        }

        return $data;
    }
}
