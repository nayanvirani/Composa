<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\DocArticleResource\Pages;
use App\Filament\Support\Fields;
use App\Models\DocArticle;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Help centre articles. */
class DocArticleResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'edit_cms';

    protected static ?string $model = DocArticle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Docs';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make()->columnSpan(2)->schema([
                Fields::title(),
                MarkdownEditor::make('body')->required()->fileAttachmentsDirectory('docs'),
            ]),
            Section::make('Publishing')->columnSpan(1)->schema([
                Fields::slug('/docs/'),
                Select::make('doc_category_id')->relationship('category', 'title')->required()->preload()
                    ->createOptionForm([TextInput::make('title')->required(), TextInput::make('slug')->required(), TextInput::make('description')]),
                Fields::status(),
                TextInput::make('plan_note')->placeholder('Growth plan and above'),
                TextInput::make('read_minutes')->numeric()->default(5),
                Toggle::make('start_here')->label('Show in "Start here"'),
                Fields::sort(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort')->reorderable('sort')
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('category.title')->badge()->color('gray'),
                Fields::statusColumn(),
                TextColumn::make('helpful_yes')->label('Helpful')->formatStateUsing(fn ($state, DocArticle $r) => $r->helpful_yes + $r->helpful_no ? round($r->helpful_yes / ($r->helpful_yes + $r->helpful_no) * 100).'%' : '—'),
                TextColumn::make('updated_at')->since(),
            ])
            ->filters([SelectFilter::make('doc_category_id')->label('Category')->relationship('category', 'title')])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocArticles::route('/'),
            'create' => Pages\CreateDocArticle::route('/create'),
            'edit' => Pages\EditDocArticle::route('/{record}/edit'),
        ];
    }
}
