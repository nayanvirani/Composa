<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\PostResource\Pages;
use App\Filament\Support\Fields;
use App\Models\Post;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Journal posts on the public website. */
class PostResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'edit_cms';

    protected static ?string $model = Post::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Journal';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make()->columnSpan(2)->schema([
                Fields::title(),
                Textarea::make('excerpt')->rows(2)->maxLength(500),
                MarkdownEditor::make('body')->required()->fileAttachmentsDirectory('journal'),
            ]),
            Section::make('Publishing')->columnSpan(1)->schema([
                Fields::slug('/journal/'),
                Fields::status(),
                DateTimePicker::make('published_at'),
                Select::make('tag')->options(['Guides' => 'Guides', 'Product' => 'Product', 'Merchants' => 'Merchants', 'Engineering' => 'Engineering'])->searchable(),
                TextInput::make('author')->default('Composa team'),
                TextInput::make('cover_image')->label('Cover image URL')->url(),
                TextInput::make('read_minutes')->numeric()->default(5),
                Toggle::make('featured'),
                Fields::seo(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('published_at', 'desc')
            ->columns([
                TextColumn::make('title')->searchable()->limit(60),
                TextColumn::make('tag')->badge()->color('gray'),
                IconColumn::make('featured')->boolean(),
                Fields::statusColumn(),
                TextColumn::make('published_at')->date()->sortable(),
            ])
            ->filters([SelectFilter::make('status')->options(['draft' => 'Draft', 'published' => 'Published'])])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosts::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'edit' => Pages\EditPost::route('/{record}/edit'),
        ];
    }
}
