<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\StatusIncidentResource\Pages;
use App\Models\StatusComponent;
use App\Models\StatusIncident;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class StatusIncidentResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'view_monitoring';

    protected static ?string $model = StatusIncident::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'Status page';

    protected static ?string $navigationLabel = 'Incidents';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('title')->required()->columnSpanFull(),
            MarkdownEditor::make('body')->required()->columnSpanFull()->helperText('Add updates at the top with the time.'),
            Select::make('severity')->options(['minor' => 'Minor', 'major' => 'Major', 'critical' => 'Critical'])->required()->default('minor'),
            Select::make('component_ids')->label('Affected components')->multiple()->options(fn () => StatusComponent::query()->orderBy('sort')->pluck('name', 'id')->all()),
            DateTimePicker::make('started_at')->required()->default(now()),
            DateTimePicker::make('resolved_at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('started_at', 'desc')
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('severity')->badge()->color(fn ($state) => $state === 'minor' ? 'warning' : 'danger'),
                TextColumn::make('started_at')->dateTime(),
                TextColumn::make('resolved_at')->dateTime()->placeholder('Ongoing'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageStatusIncidents::route('/')];
    }
}
