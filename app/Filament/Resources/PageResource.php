<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\PageResource\Pages;
use App\Filament\Support\Fields;
use App\Models\Page;
use App\Support\PageSections;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Website pages built from section blocks. Every save keeps a revision that can be restored. */
class PageResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'edit_cms';

    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make()->columnSpan(2)->schema([
                Fields::title(),
                Builder::make('sections')
                    ->label('Sections')
                    ->blocks(self::blocks())
                    ->collapsible()
                    ->collapsed()
                    ->cloneable()
                    ->blockNumbers(false)
                    ->addActionLabel('Add section')
                    ->visible(fn ($get) => $get('template') !== 'legal'),
                MarkdownEditor::make('sections.0.data.body')
                    ->label('Document')
                    ->visible(fn ($get) => $get('template') === 'legal')
                    ->helperText('Legal pages are a single document. Use ## for numbered headings.'),
            ]),
            Section::make('Publishing')->columnSpan(1)->schema([
                Fields::slug(),
                Select::make('locale')->options(['en' => 'English', 'fr' => 'French', 'de' => 'German', 'es' => 'Spanish', 'it' => 'Italian', 'nl' => 'Dutch', 'pt' => 'Portuguese'])->default('en')->required(),
                Select::make('template')->options(['sections' => 'Sections', 'legal' => 'Legal document'])->default('sections')->required()->live(),
                Fields::status(),
                DateTimePicker::make('publish_at')->label('Publish at')->helperText('Leave empty to publish immediately.'),
                Fields::seo(),
            ]),
        ]);
    }

    /** @return list<Block> */
    public static function blocks(): array
    {
        $hidden = Toggle::make('hidden')->label('Hide this section');
        $heading = fn () => [TextInput::make('eyebrow')->maxLength(60), TextInput::make('title')->required(), Textarea::make('intro')->rows(2)];

        return [
            Block::make('hero')->label(PageSections::label('hero'))->icon(Heroicon::OutlinedSparkles)->schema([
                TextInput::make('eyebrow')->maxLength(60),
                Textarea::make('title')->required()->rows(2)->helperText('Wrap words in *asterisks* to highlight them.'),
                Textarea::make('subtitle')->rows(3),
                Grid::make(2)->schema([
                    TextInput::make('primary_label')->default('Install on Shopify'),
                    TextInput::make('primary_url')->default('https://apps.shopify.com/composa'),
                    TextInput::make('secondary_label'),
                    TextInput::make('secondary_url'),
                ]),
                TextInput::make('note')->label('Small print under the buttons'),
                TextInput::make('image')->label('Image URL')->url(),
                $hidden,
            ]),
            Block::make('spec_strip')->label(PageSections::label('spec_strip'))->icon(Heroicon::OutlinedHashtag)->schema([
                Repeater::make('items')->schema([TextInput::make('value')->required(), TextInput::make('label')->required()])->columns(2)->maxItems(6)->defaultItems(4),
                $hidden,
            ]),
            Block::make('features')->label(PageSections::label('features'))->icon(Heroicon::OutlinedSquares2x2)->schema([
                ...$heading(),
                Repeater::make('items')->schema([TextInput::make('code')->label('Code')->placeholder('F-01')->maxLength(8), TextInput::make('title')->required(), Textarea::make('body')->rows(2)])->columns(3)->collapsible(),
                $hidden,
            ]),
            Block::make('steps')->label(PageSections::label('steps'))->icon(Heroicon::OutlinedListBullet)->schema([
                ...$heading(),
                Repeater::make('items')->schema([TextInput::make('title')->required(), Textarea::make('body')->rows(2)])->columns(2)->maxItems(8),
                $hidden,
            ]),
            Block::make('variant_demo')->label(PageSections::label('variant_demo'))->icon(Heroicon::OutlinedArrowsRightLeft)->schema([
                ...$heading(),
                Repeater::make('options')->label('Shopify options shown')->schema([TextInput::make('name')->required(), TextInput::make('values')->required()->helperText('Comma separated')])->columns(2)->maxItems(3),
                $hidden,
            ]),
            Block::make('templates')->label(PageSections::label('templates'))->icon(Heroicon::OutlinedRectangleGroup)->schema([
                ...$heading(),
                TextInput::make('count')->numeric()->default(6)->minValue(1)->maxValue(24),
                Toggle::make('featured_only')->label('Featured templates only'),
                $hidden,
            ]),
            Block::make('pricing')->label(PageSections::label('pricing'))->icon(Heroicon::OutlinedCurrencyDollar)->schema([
                ...$heading(),
                Textarea::make('footnote')->rows(2)->default('Billed through Shopify. Prices in USD.'),
                Toggle::make('show_limits')->label('Show the full limits table')->default(true),
                $hidden,
            ]),
            Block::make('comparison')->label(PageSections::label('comparison'))->icon(Heroicon::OutlinedTableCells)->schema([
                ...$heading(),
                Grid::make(2)->schema([TextInput::make('us_label')->default('Composa'), TextInput::make('them_label')->default('Typical options app')]),
                Repeater::make('rows')->schema([TextInput::make('feature')->required(), TextInput::make('us')->required(), TextInput::make('them')->required()])->columns(3),
                $hidden,
            ]),
            Block::make('testimonials')->label(PageSections::label('testimonials'))->icon(Heroicon::OutlinedChatBubbleLeftRight)->schema([
                ...$heading(),
                TextInput::make('count')->numeric()->default(3),
                $hidden,
            ]),
            Block::make('faq')->label(PageSections::label('faq'))->icon(Heroicon::OutlinedQuestionMarkCircle)->schema([
                ...$heading(),
                Select::make('placement')->options(PageSections::FAQ_PLACEMENTS)->default('home')->required(),
                $hidden,
            ]),
            Block::make('posts')->label(PageSections::label('posts'))->icon(Heroicon::OutlinedNewspaper)->schema([
                ...$heading(),
                TextInput::make('count')->numeric()->default(3),
                $hidden,
            ]),
            Block::make('changelog')->label(PageSections::label('changelog'))->icon(Heroicon::OutlinedClock)->schema([
                ...$heading(),
                TextInput::make('count')->numeric()->default(10),
                $hidden,
            ]),
            Block::make('status')->label(PageSections::label('status'))->icon(Heroicon::OutlinedSignal)->schema([...$heading(), $hidden]),
            Block::make('rich_text')->label(PageSections::label('rich_text'))->icon(Heroicon::OutlinedBars3BottomLeft)->schema([
                TextInput::make('title'),
                MarkdownEditor::make('body')->required(),
                $hidden,
            ]),
            Block::make('form')->label(PageSections::label('form'))->icon(Heroicon::OutlinedEnvelope)->schema([
                ...$heading(),
                Select::make('kind')->options(PageSections::FORMS)->required(),
                TextInput::make('success')->label('Thank-you message')->default('Thanks — we’ll reply within one business day.'),
                $hidden,
            ]),
            Block::make('cta')->label(PageSections::label('cta'))->icon(Heroicon::OutlinedCursorArrowRays)->schema([
                TextInput::make('title')->required(),
                Textarea::make('body')->rows(2),
                Grid::make(2)->schema([TextInput::make('button_label')->default('Install on Shopify'), TextInput::make('button_url')->default('https://apps.shopify.com/composa')]),
                $hidden,
            ]),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('slug')
            ->columns([
                TextColumn::make('title')->searchable()->description(fn (Page $p) => '/'.ltrim($p->slug === 'home' ? '' : $p->slug, '/')),
                TextColumn::make('locale')->badge()->color('gray'),
                TextColumn::make('template')->color('gray'),
                Fields::statusColumn(),
                TextColumn::make('updated_at')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(['draft' => 'Draft', 'published' => 'Published']),
                SelectFilter::make('locale')->options(fn () => Page::query()->distinct()->pluck('locale', 'locale')->all()),
            ])
            ->recordActions([
                Action::make('view')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->url(fn (Page $p) => url($p->slug === 'home' ? '/' : $p->slug), true),
                EditAction::make(),
                ReplicateAction::make()->label('Translate')->icon(Heroicon::OutlinedLanguage)
                    ->schema([Select::make('locale')->options(['fr' => 'French', 'de' => 'German', 'es' => 'Spanish', 'it' => 'Italian', 'nl' => 'Dutch', 'pt' => 'Portuguese'])->required()])
                    ->beforeReplicaSaved(fn (Page $replica, array $data) => $replica->fill(['locale' => $data['locale'], 'status' => 'draft'])),
            ]);
    }

    public static function getRelations(): array
    {
        return [PageResource\RelationManagers\RevisionsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
