<?php

namespace App\Filament\Resources\SystemEventResource\Pages;

use App\Filament\Resources\SystemEventResource;
use Filament\Resources\Pages\ManageRecords;

class ManageSystemEvents extends ManageRecords
{
    protected static string $resource = SystemEventResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
