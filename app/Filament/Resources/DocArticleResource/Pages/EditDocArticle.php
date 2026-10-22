<?php

namespace App\Filament\Resources\DocArticleResource\Pages;

use App\Filament\Resources\DocArticleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDocArticle extends EditRecord
{
    protected static string $resource = DocArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
