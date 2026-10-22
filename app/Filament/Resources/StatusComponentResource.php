<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\StatusComponentResource\Pages;
use App\Filament\Support\Fields;
use App\Models\StatusComponent;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Components on the public status page. */
class StatusComponentResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'view_monitoring';

    protected static ?string $model = StatusComponent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static string|UnitEnum|null $navigationGroup = 'Status page';

    protected static ?string $navigationLabel = 'Components';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            TextInput::make('description'),
            Select::make('status')->options(['operational' => 'Operational', 'degraded' => 'Degraded', 'outage' => 'Outage'])->required()->default('operational'),
            Fields::sort(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort')->reorderable('sort')
            ->columns([TextColumn::make('name'), TextColumn::make('description')->color('gray'), Fields::statusColumn()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageStatusComponents::route('/')];
    }
}
