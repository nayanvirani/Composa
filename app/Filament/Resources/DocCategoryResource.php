<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\DocCategoryResource\Pages;
use App\Filament\Support\Fields;
use App\Models\DocCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class DocCategoryResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'edit_cms';

    protected static ?string $model = DocCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Doc categories';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([Fields::title(), Fields::slug('/docs/'), TextInput::make('description'), Fields::sort()]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort')->reorderable('sort')
            ->columns([TextColumn::make('title'), TextColumn::make('slug')->color('gray'), TextColumn::make('articles_count')->counts('articles')->label('Articles')])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageDocCategories::route('/')];
    }
}
