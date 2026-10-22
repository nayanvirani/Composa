<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\ChangelogEntryResource\Pages;
use App\Filament\Support\Fields;
use App\Models\ChangelogEntry;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ChangelogEntryResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'edit_cms';

    protected static ?string $model = ChangelogEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Changelog';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Grid::make(3)->schema([
                TextInput::make('version')->required()->placeholder('1.4.0'),
                DatePicker::make('released_at')->required()->default(now()),
                Fields::status(),
            ]),
            TextInput::make('title')->required(),
            Repeater::make('items')->schema([
                Select::make('kind')->options(['added' => 'Added', 'changed' => 'Changed', 'fixed' => 'Fixed'])->required()->default('added'),
                TextInput::make('text')->required()->columnSpan(3),
            ])->columns(4)->defaultItems(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('released_at', 'desc')
            ->columns([TextColumn::make('version')->badge(), TextColumn::make('title')->searchable(), TextColumn::make('released_at')->date(), Fields::statusColumn()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageChangelogEntries::route('/')];
    }
}
