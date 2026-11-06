<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\FeatureFlagResource\Pages;
use App\Models\FeatureFlag;
use App\Models\Plan;
use App\Models\Shop;
use App\Support\Audit;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

/** Feature flags: off, on for everyone, or targeted at plans and individual shops. */
class FeatureFlagResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'manage_flags';

    protected static ?string $model = FeatureFlag::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            TextInput::make('key')->required()->unique(ignoreRecord: true)->regex('/^[a-z0-9_.]+$/')->disabledOn('edit'),
            TextInput::make('description'),
            Select::make('state')->options(['off' => 'Off', 'on' => 'On for everyone', 'targeted' => 'Targeted'])->required()->default('off')->live(),
            CheckboxList::make('plans')->options(fn () => Plan::query()->orderBy('sort')->pluck('name', 'handle')->all())->columns(4)
                ->visible(fn (Get $get) => $get('state') === 'targeted'),
            Select::make('shop_ids')->label('Shops')->multiple()->searchable()
                ->getSearchResultsUsing(fn (string $search) => Shop::query()->where('domain', 'ilike', "%{$search}%")->limit(20)->pluck('domain', 'id')->all())
                ->getOptionLabelsUsing(fn (array $values) => Shop::query()->whereIn('id', $values)->pluck('domain', 'id')->all())
                ->visible(fn (Get $get) => $get('state') === 'targeted'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('key')
            ->columns([
                TextColumn::make('key')->searchable()->fontFamily('mono')->description(fn (FeatureFlag $f) => $f->description),
                TextColumn::make('state')->badge()->color(fn ($state) => match ($state) {
                    'on' => 'success',
                    'targeted' => 'warning',
                    default => 'gray',
                }),
                TextColumn::make('plans')->badge()->color('gray')->placeholder('—'),
                TextColumn::make('shop_ids')->label('Shops')->formatStateUsing(fn ($state, FeatureFlag $f) => count($f->shop_ids ?? []))->placeholder('0'),
                TextColumn::make('updated_at')->since(),
            ])
            ->recordActions([
                EditAction::make()->after(fn (FeatureFlag $record) => self::changed($record)),
                DeleteAction::make()->after(fn (FeatureFlag $record) => self::changed($record)),
            ]);
    }

    public static function changed(FeatureFlag $flag): void
    {
        Cache::forget('flag:'.$flag->key);
        Audit::log('flag.changed', $flag->key.' → '.$flag->state, $flag, meta: ['plans' => $flag->plans, 'shops' => $flag->shop_ids]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageFeatureFlags::route('/')];
    }
}
