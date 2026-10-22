<?php

namespace App\Filament\Resources\DocCategoryResource\Pages;

use App\Filament\Resources\DocCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDocCategories extends ManageRecords
{
    protected static string $resource = DocCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
