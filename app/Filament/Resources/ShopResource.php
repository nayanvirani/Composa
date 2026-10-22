<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\ShopResource\Pages;
use App\Filament\Resources\ShopResource\RelationManagers;
use App\Filament\Support\Fields;
use App\Models\Plan;
use App\Models\Shop;
use App\Services\PlanLimits;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Installed stores: details, usage against plan limits, overrides, notes and impersonation. */
class ShopResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'view_shops';

    protected static ?string $model = Shop::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Merchants';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'domain';

    public static function getGloballySearchableAttributes(): array
    {
        return ['domain', 'name', 'email'];
    }

    public static function canCreate(): bool
    {
        return false; // shops arrive through installation only
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Store')->columnSpan(2)->columns(3)->schema([
                TextEntry::make('name'),
                TextEntry::make('domain')->copyable(),
                TextEntry::make('email')->copyable(),
                TextEntry::make('owner_name'),
                TextEntry::make('shopify_plan')->badge()->color('gray'),
                TextEntry::make('status')->badge()->color(fn ($state) => $state === 'active' ? 'success' : 'danger'),
                TextEntry::make('currency'),
                TextEntry::make('primary_locale')->label('Locale'),
                TextEntry::make('timezone'),
                TextEntry::make('installed_at')->dateTime(),
                TextEntry::make('uninstalled_at')->dateTime()->placeholder('—'),
                TextEntry::make('purge_after')->dateTime()->placeholder('—')->label('Data deleted at'),
            ]),
            Section::make('Billing')->columnSpan(1)->schema([
                TextEntry::make('plan.name')->label('Plan')->badge()->placeholder('None'),
                TextEntry::make('subscription_status')->placeholder('—'),
                TextEntry::make('trial_ends_at')->dateTime()->placeholder('—'),
                TextEntry::make('pricing')->label('Pricing page')->state(fn (Shop $s) => 'Open in Shopify')->url(fn (Shop $s) => $s->pricingPageUrl(), true),
            ]),
            Section::make('Usage against plan')->columnSpan(2)->columns(4)->schema(fn (Shop $record) => collect(PlanLimits::for($record)->meters())
                ->map(fn ($m, $key) => TextEntry::make('usage_'.$key)->label(str($key)->replace('_', ' ')->title())
                    ->state(($key === 'storage_mb' ? round($m['used']).' MB' : $m['used']).' / '.($m['limit'] ?? '∞'))
                    ->badge()->color(match ($m['state']) {
                        'reached' => 'danger', 'warning' => 'warning', default => 'gray'
                    })
                    ->helperText(isset($record->limit_overrides[$key]) ? 'Override' : null))
                ->values()->all()),
            Section::make('Checkout')->columnSpan(1)->schema([
                TextEntry::make('cart_mode')->badge()->helperText(fn (Shop $s) => match ($s->cart_mode) {
                    'update' => 'Cart Transform lineUpdate (Plus / dev store)',
                    'expand' => 'Cart Transform lineExpand',
                    default => 'Discount function fallback',
                }),
                IconEntry::make('cart_transform_id')->label('Cart transform active')->boolean()->state(fn (Shop $s) => (bool) $s->cart_transform_id),
                IconEntry::make('conflict')->label('Conflict with another app')->boolean()->state(fn (Shop $s) => (bool) $s->setting('cart_transform_conflict'))->trueColor('danger')->falseColor('success'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('installed_at', 'desc')
            ->modifyQueryUsing(fn (Builder $q) => $q->with('plan')->withCount(['configurators as published_count' => fn ($c) => $c->where('status', 'published')]))
            ->columns([
                TextColumn::make('name')->searchable()->description(fn (Shop $s) => $s->domain)->sortable(),
                TextColumn::make('plan.name')->badge()->placeholder('None'),
                Fields::statusColumn(),
                TextColumn::make('shopify_plan')->color('gray')->toggleable(),
                IconColumn::make('is_plus')->label('Plus')->boolean()->toggleable(),
                TextColumn::make('published_count')->label('Live configurators')->alignEnd(),
                TextColumn::make('cart_mode')->badge()->color('gray')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('installed_at')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('plan_id')->label('Plan')->options(fn () => Plan::query()->orderBy('sort')->pluck('name', 'id')->all()),
                SelectFilter::make('status')->options(['active' => 'Active', 'uninstalled' => 'Uninstalled', 'frozen' => 'Frozen'])->default('active'),
                TernaryFilter::make('is_plus')->label('Shopify Plus'),
                TernaryFilter::make('is_dev')->label('Development store'),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ConfiguratorsRelationManager::class,
            RelationManagers\NotesRelationManager::class,
            RelationManagers\SubscriptionsRelationManager::class,
            RelationManagers\TicketsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShops::route('/'),
            'view' => Pages\ViewShop::route('/{record}'),
        ];
    }
}
