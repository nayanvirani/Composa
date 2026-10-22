<?php

namespace App\Filament\Resources\ShopResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Subscriptions as reported by Shopify App Pricing (read-only). */
class SubscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptions';

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->can('view_finance') || (bool) auth()->user()?->can('manage_shops');
    }

    public function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('name'),
            TextColumn::make('status')->badge(),
            TextColumn::make('price_cents')->label('Price')->formatStateUsing(fn ($state, $r) => number_format($state / 100, 2).' '.$r->currency),
            TextColumn::make('trial_ends_at')->dateTime()->placeholder('—'),
            TextColumn::make('current_period_end')->dateTime()->placeholder('—'),
            TextColumn::make('created_at')->since(),
        ]);
    }
}
