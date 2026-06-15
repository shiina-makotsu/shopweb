<?php

namespace App\Filament\Resources\ApprovalRequestResource\Pages;

use App\Filament\Resources\ApprovalRequestResource;
use App\Filament\Resources\AfterSalesRequestResource\Pages\EditAfterSalesRequest;

class EditApprovalRequest extends EditAfterSalesRequest
{
    protected static string $resource = ApprovalRequestResource::class;
}
