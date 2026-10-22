<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\FaqResource\Pages;
use App\Filament\Support\Fields;
use App\Models\Faq;
use App\Support\PageSections;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** FAQs shown on the website (home, pricing, help) and inside the merchant app. */
class FaqResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'edit_cms';

    protected static ?string $model = Faq::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'FAQs';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            TextInput::make('question')->required(),
            MarkdownEditor::make('answer')->required(),
            CheckboxList::make('placements')->options(PageSections::FAQ_PLACEMENTS)->columns(4)->required(),
            Grid::make(3)->schema([
                Select::make('locale')->options(['en' => 'English', 'fr' => 'French', 'de' => 'German', 'es' => 'Spanish'])->default('en'),
                Fields::status(),
                Fields::sort(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort')->reorderable('sort')
            ->columns([
                TextColumn::make('question')->searchable()->wrap(),
                TextColumn::make('placements')->badge()->color('gray'),
                TextColumn::make('locale')->badge()->color('gray'),
                Fields::statusColumn(),
            ])
            ->filters([SelectFilter::make('locale')->options(['en' => 'English', 'fr' => 'French', 'de' => 'German', 'es' => 'Spanish'])])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageFaqs::route('/')];
    }
}
