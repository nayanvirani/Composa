<?php

namespace App\Filament\Resources\BetaApplicationResource\Pages;

use App\Filament\Resources\BetaApplicationResource;
use Filament\Resources\Pages\ManageRecords;

class ManageBetaApplications extends ManageRecords
{
    protected static string $resource = BetaApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
