<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\SystemEventResource;
use App\Models\SystemEvent;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class OpenIssues extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Open monitoring issues';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('view_monitoring');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(SystemEvent::query()->whereNull('resolved_at')->with('shop')->orderByRaw("case severity when 'critical' then 0 when 'warning' then 1 else 2 end")->orderByDesc('last_seen_at'))
            ->paginated([5])
            ->columns([
                TextColumn::make('severity')->badge()->color(fn ($state) => match ($state) {
                    'critical' => 'danger', 'warning' => 'warning', default => 'gray'
                }),
                TextColumn::make('type')->color('gray'),
                TextColumn::make('message')->limit(80),
                TextColumn::make('shop.domain')->placeholder('—'),
                TextColumn::make('count'),
                TextColumn::make('last_seen_at')->since(),
            ])
            ->recordUrl(fn () => SystemEventResource::getUrl());
    }
}
