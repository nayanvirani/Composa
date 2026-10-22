<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\GdprRequestResource\Pages;
use App\Filament\Support\Fields;
use App\Jobs\ProcessGdprRequest;
use App\Models\GdprRequest;
use App\Support\Audit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\CodeEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Mandatory compliance webhooks (data request, customer redact, shop redact) with their 30-day deadline. */
class GdprRequestResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'gdpr_actions';

    protected static ?string $model = GdprRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'GDPR requests';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = GdprRequest::query()->where('status', '!=', 'done')->count();

        return $pending ? (string) $pending : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextEntry::make('shop_domain'),
            TextEntry::make('topic')->badge(),
            TextEntry::make('status')->badge(),
            TextEntry::make('due_at')->dateTime(),
            TextEntry::make('processed_at')->dateTime()->placeholder('—'),
            TextEntry::make('result')->placeholder('—')->columnSpanFull(),
            CodeEntry::make('payload')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('due_at')
            ->columns([
                TextColumn::make('shop_domain')->searchable(),
                TextColumn::make('topic')->badge()->color('gray'),
                Fields::statusColumn(),
                TextColumn::make('due_at')->label('Due')->since()->color(fn (GdprRequest $r) => $r->status !== 'done' && $r->due_at->lt(now()->addDays(5)) ? 'danger' : null),
                TextColumn::make('processed_at')->since()->placeholder('—'),
            ])
            ->filters([SelectFilter::make('status')->options(['pending' => 'Pending', 'done' => 'Done', 'failed' => 'Failed'])])
            ->recordActions([
                ViewAction::make(),
                Action::make('process')->label('Process now')->icon(Heroicon::OutlinedPlay)->requiresConfirmation()
                    ->visible(fn (GdprRequest $r) => $r->status !== 'done')
                    ->action(function (GdprRequest $record) {
                        ProcessGdprRequest::dispatch($record->id);
                        Audit::log('gdpr.processed', $record->topic.' for '.$record->shop_domain, $record);
                        Notification::make()->title('Queued')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageGdprRequests::route('/')];
    }
}
