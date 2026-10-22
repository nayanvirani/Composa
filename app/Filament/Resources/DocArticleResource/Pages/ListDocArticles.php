<?php

namespace App\Filament\Resources\DocArticleResource\Pages;

use App\Filament\Resources\DocArticleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDocArticles extends ListRecords
{
    protected static string $resource = DocArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
