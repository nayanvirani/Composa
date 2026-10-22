<?php

namespace App\Filament\Resources\ShopResource\RelationManagers;

use App\Filament\Resources\SupportTicketResource;
use App\Filament\Support\Fields;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TicketsRelationManager extends RelationManager
{
    protected static string $relationship = 'tickets';

    protected static ?string $title = 'Support tickets';

    public function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('subject')->limit(60),
                Fields::statusColumn(),
                TextColumn::make('priority')->badge()->color('gray'),
                TextColumn::make('last_reply_at')->since()->placeholder('—'),
            ])
            ->recordActions([Action::make('open')->url(fn ($record) => SupportTicketResource::getUrl('view', ['record' => $record]))]);
    }
}
