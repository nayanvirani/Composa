<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\AnnouncementResource\Pages;
use App\Models\Announcement;
use App\Models\Plan;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Banners on the website or inside the merchant app (optionally only for some plans). */
class AnnouncementResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'edit_cms';

    protected static ?string $model = Announcement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 8;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('scope')->options(['site' => 'Website', 'app' => 'Merchant app'])->required()->live(),
            Toggle::make('active')->default(true)->inline(false),
            TextInput::make('message')->required()->maxLength(255)->columnSpanFull(),
            TextInput::make('link_label'),
            TextInput::make('link_url'),
            DateTimePicker::make('starts_at'),
            DateTimePicker::make('ends_at')->after('starts_at'),
            CheckboxList::make('audience')->label('Only for plans')->helperText('Leave empty to show everyone.')
                ->options(fn () => Plan::query()->orderBy('sort')->pluck('name', 'handle')->all())
                ->visible(fn ($get) => $get('scope') === 'app')->columns(4)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('message')->limit(70),
                TextColumn::make('scope')->badge(),
                IconColumn::make('active')->boolean(),
                TextColumn::make('starts_at')->dateTime()->placeholder('Now'),
                TextColumn::make('ends_at')->dateTime()->placeholder('No end'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAnnouncements::route('/')];
    }
}
