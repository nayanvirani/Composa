<?php

namespace App\Filament\Resources\StatusIncidentResource\Pages;

use App\Filament\Resources\StatusIncidentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageStatusIncidents extends ManageRecords
{
    protected static string $resource = StatusIncidentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
