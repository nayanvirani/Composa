<?php

namespace App\Filament\Resources\GdprRequestResource\Pages;

use App\Filament\Resources\GdprRequestResource;
use Filament\Resources\Pages\ManageRecords;

class ManageGdprRequests extends ManageRecords
{
    protected static string $resource = GdprRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
