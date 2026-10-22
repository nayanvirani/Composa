<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\BetaApplicationResource\Pages;
use App\Filament\Support\Fields;
use App\Models\BetaApplication;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Early-access applications from the website. */
class BetaApplicationResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'manage_support';

    protected static ?string $model = BetaApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|UnitEnum|null $navigationGroup = 'Support';

    protected static ?string $navigationLabel = 'Early access';

    protected static ?int $navigationSort = 4;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('status')->options(['new' => 'New', 'contacted' => 'Contacted', 'accepted' => 'Accepted', 'declined' => 'Declined'])->required(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('store'), TextEntry::make('email')->copyable(),
            TextEntry::make('shopify_plan')->placeholder('—'), TextEntry::make('has_variants')->placeholder('—'),
            TextEntry::make('categories')->badge()->placeholder('—')->columnSpanFull(),
            TextEntry::make('current_process')->columnSpanFull()->placeholder('—'),
            TextEntry::make('must_have')->columnSpanFull()->placeholder('—'),
            TextEntry::make('status')->badge(), TextEntry::make('created_at')->dateTime(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('store')->searchable()->description(fn (BetaApplication $b) => $b->email),
                TextColumn::make('categories')->badge()->color('gray'),
                TextColumn::make('has_variants')->label('Variants'),
                Fields::statusColumn(),
                TextColumn::make('created_at')->since(),
            ])
            ->filters([SelectFilter::make('status')->options(['new' => 'New', 'contacted' => 'Contacted', 'accepted' => 'Accepted', 'declined' => 'Declined'])])
            ->recordActions([ViewAction::make(), EditAction::make()->label('Status')]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageBetaApplications::route('/')];
    }
}
