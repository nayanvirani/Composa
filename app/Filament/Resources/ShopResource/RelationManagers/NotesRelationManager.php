<?php

namespace App\Filament\Resources\ShopResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Internal notes about a merchant, visible to staff only. */
class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    public function isReadOnly(): bool
    {
        return ! auth()->user()?->can('manage_shops');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([Textarea::make('body')->label('Note')->required()->rows(4)]);
    }

    public function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('body')->wrap(),
                TextColumn::make('user.name')->label('By'),
                TextColumn::make('created_at')->since(),
            ])
            ->headerActions([CreateAction::make()->label('Add note')->mutateDataUsing(fn (array $data) => $data + ['user_id' => auth()->id()])])
            ->recordActions([DeleteAction::make()->visible(fn ($record) => $record->user_id === auth()->id())]);
    }
}
