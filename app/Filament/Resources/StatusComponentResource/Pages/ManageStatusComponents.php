<?php

namespace App\Filament\Resources\StatusComponentResource\Pages;

use App\Filament\Resources\StatusComponentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageStatusComponents extends ManageRecords
{
    protected static string $resource = StatusComponentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
