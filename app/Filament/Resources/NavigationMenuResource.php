<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\NavigationMenuResource\Pages;
use App\Models\NavigationMenu;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Header and footer menus of the public website. */
class NavigationMenuResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'edit_cms';

    protected static ?string $model = NavigationMenu::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Menus';

    protected static ?int $navigationSort = 7;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Grid::make(2)->schema([
                TextInput::make('key')->required()->unique(ignoreRecord: true)->helperText('header, footer_product, footer_resources, footer_company, footer_legal'),
                TextInput::make('title')->required(),
            ]),
            Repeater::make('items')->schema([
                TextInput::make('label')->required(),
                TextInput::make('url')->required()->placeholder('/pricing'),
                Toggle::make('new_tab')->inline(false),
            ])->columns(3)->reorderable()->defaultItems(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title'),
            TextColumn::make('key')->color('gray'),
            TextColumn::make('items')->label('Links')->formatStateUsing(fn ($state, NavigationMenu $r) => count($r->items ?? [])),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageNavigationMenus::route('/')];
    }
}
