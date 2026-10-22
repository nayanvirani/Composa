<?php

namespace App\Filament\Resources\ShopResource\RelationManagers;

use App\Filament\Support\Fields;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConfiguratorsRelationManager extends RelationManager
{
    protected static string $relationship = 'configurators';

    public function table(Table $table): Table
    {
        return $table->defaultSort('updated_at', 'desc')->columns([
            TextColumn::make('name')->description(fn ($r) => $r->handle),
            Fields::statusColumn(),
            TextColumn::make('template_key')->label('Template')->color('gray'),
            TextColumn::make('display_mode')->badge()->color('gray'),
            TextColumn::make('base_product_title')->label('Base product')->limit(30),
            TextColumn::make('published_at')->since()->placeholder('—'),
        ]);
    }
}
