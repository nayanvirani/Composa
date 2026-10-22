<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\SystemEventResource\Pages;
use App\Models\SystemEvent;
use App\Models\User;
use App\Support\Audit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\CodeEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Monitoring: price mismatches, signature failures, webhook and job failures, slow loads, conflicts. */
class SystemEventResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'view_monitoring';

    protected static ?string $model = SystemEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'Monitoring';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $critical = SystemEvent::query()->whereNull('resolved_at')->where('severity', 'critical')->count();

        return $critical ? (string) $critical : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextEntry::make('message')->columnSpanFull(),
            TextEntry::make('type')->badge(),
            TextEntry::make('severity')->badge(),
            TextEntry::make('count'),
            TextEntry::make('shop.domain')->label('Shop')->placeholder('—'),
            TextEntry::make('first_seen_at')->dateTime(),
            TextEntry::make('last_seen_at')->dateTime(),
            TextEntry::make('assignee.name')->label('Assigned to')->placeholder('—'),
            TextEntry::make('resolved_at')->dateTime()->placeholder('Open'),
            CodeEntry::make('context')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('last_seen_at', 'desc')
            ->columns([
                TextColumn::make('severity')->badge()->color(fn ($state) => match ($state) {
                    'critical' => 'danger', 'warning' => 'warning', default => 'gray'
                }),
                TextColumn::make('type')->badge()->color('gray'),
                TextColumn::make('message')->limit(70)->searchable()->description(fn (SystemEvent $e) => $e->shop?->domain),
                TextColumn::make('count')->alignEnd()->sortable(),
                TextColumn::make('last_seen_at')->since()->sortable(),
                TextColumn::make('assignee.name')->label('Owner')->placeholder('—'),
            ])
            ->filters([
                TernaryFilter::make('open')->label('Open only')->default(true)->queries(true: fn ($q) => $q->whereNull('resolved_at'), false: fn ($q) => $q->whereNotNull('resolved_at')),
                SelectFilter::make('severity')->options(['critical' => 'Critical', 'warning' => 'Warning', 'info' => 'Info']),
                SelectFilter::make('type')->options(fn () => SystemEvent::query()->distinct()->pluck('type', 'type')->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('assign')->icon(Heroicon::OutlinedUserPlus)->color('gray')
                    ->schema([Select::make('assigned_to')->label('Owner')->options(fn () => User::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all())])
                    ->action(fn (SystemEvent $record, array $data) => $record->update($data)),
                Action::make('resolve')->icon(Heroicon::OutlinedCheck)->color('success')
                    ->visible(fn (SystemEvent $e) => ! $e->resolved_at)
                    ->action(function (SystemEvent $record) {
                        $record->update(['resolved_at' => now()]);
                        Audit::log('monitoring.resolved', $record->message, $record, $record->shop);
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('resolve')->icon(Heroicon::OutlinedCheck)->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each->update(['resolved_at' => now()])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageSystemEvents::route('/')];
    }
}
