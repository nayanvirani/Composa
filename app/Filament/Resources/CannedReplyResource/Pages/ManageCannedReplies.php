<?php

namespace App\Filament\Resources\CannedReplyResource\Pages;

use App\Filament\Resources\CannedReplyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCannedReplies extends ManageRecords
{
    protected static string $resource = CannedReplyResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
