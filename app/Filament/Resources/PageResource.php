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
use Filament\Forms\Components\Placeholder;
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
        $hidden = fn () => Toggle::make('hidden')->label('Hide this section');
        $title = fn (string $default = '') => Textarea::make('title')->rows(2)->default($default)->helperText('Wrap words in *asterisks* for the orange italic accent.');
        $sheet = fn () => TextInput::make('sheet')->label('Sheet label')->placeholder('Sheet 03 — Pricing');
        $buttons = fn () => Grid::make(2)->schema([
            TextInput::make('primary_label'), TextInput::make('primary_url'),
            TextInput::make('secondary_label'), TextInput::make('secondary_url'),
        ]);
        $block = fn (string $type, $icon, array $schema) => Block::make($type)->label(PageSections::label($type))->icon($icon)->schema([...$schema, $hidden()]);

        return [
            $block('page_hero', Heroicon::OutlinedRectangleStack, [
                $sheet(), $title(), Textarea::make('intro')->rows(3),
                Grid::make(3)->schema([
                    Select::make('layout')->options(['split' => 'Title left, intro right', 'stacked' => 'Stacked', 'statement' => 'Large statement'])->default('split'),
                    Select::make('size')->options(['xl' => 'Extra large', 'l' => 'Large'])->default('xl'),
                    Toggle::make('paper')->label('Grid paper background')->default(true)->inline(false),
                ]),
                $buttons(),
            ]),
            $block('composer', Heroicon::OutlinedSparkles, [$title('Compose anything. *Sell it as one.*'), Textarea::make('intro')->rows(3), $buttons()]),
            $block('ledger', Heroicon::OutlinedScale, [
                Grid::make(2)->schema([TextInput::make('before_label')->default('§1 Without Composa'), TextInput::make('after_label')->default('§2 With Composa')]),
                Repeater::make('before')->schema([TextInput::make('text')->required()])->maxItems(6),
                Repeater::make('after')->schema([TextInput::make('title')->required(), TextInput::make('text')])->columns(2)->maxItems(6),
            ]),
            $block('routing', Heroicon::OutlinedArrowLongRight, [$title(), TextInput::make('meta')->placeholder('ROUTING SHEET · TARGET < 30 MIN'), TextInput::make('anchor'), Repeater::make('items')->schema([TextInput::make('title')->required(), Textarea::make('body')->rows(2)])->columns(2)->maxItems(8)]),
            $block('bom', Heroicon::OutlinedTableCells, [$title('Bill of materials'), Textarea::make('intro')->rows(2), Repeater::make('rows')->schema([TextInput::make('no')->label('Part no.'), TextInput::make('component')->required(), Textarea::make('body')->rows(2), TextInput::make('plan')->label('From plan')])->columns(4)->collapsible()]),
            $block('formula', Heroicon::OutlinedCalculator, [TextInput::make('eyebrow'), $title(), Textarea::make('body')->rows(3), TextInput::make('formula'), TextInput::make('badge'), Repeater::make('terms')->schema([TextInput::make('symbol')->required(), TextInput::make('text')->required()])->columns(2)->maxItems(6)]),
            $block('patterns', Heroicon::OutlinedRectangleGroup, [$title('Start from a pattern'), Grid::make(3)->schema([TextInput::make('link_label')->default('All templates →'), TextInput::make('link_url')->default('/templates'), TextInput::make('count')->numeric()->default(6)])]),
            $block('template_library', Heroicon::OutlinedSquares2x2, [Placeholder::make('info')->content('Lists every published template from Catalog → Templates, with category filters and a spec panel.')]),
            $block('spec_card', Heroicon::OutlinedClipboardDocumentCheck, [TextInput::make('eyebrow'), $title(), Textarea::make('body')->rows(3), Grid::make(2)->schema([TextInput::make('card_title'), TextInput::make('badge')]), Repeater::make('rows')->schema([TextInput::make('k')->label('Label')->required(), TextInput::make('v')->label('Value')->required()])->columns(2), Grid::make(2)->schema([TextInput::make('footer_left'), TextInput::make('footer_right')])]),
            $block('beta_inline', Heroicon::OutlinedPencilSquare, [$title('Order form: *beta access*'), Textarea::make('body')->rows(3)]),
            $block('pricing', Heroicon::OutlinedCurrencyDollar, [$sheet(), $title('Specify your *requirements.*'), Textarea::make('intro')->rows(2), TextInput::make('footnote'), TextInput::make('table_title')->default('Full specification'), Placeholder::make('info')->content('Plans, prices and limits come from Merchants → Plans.')]),
            $block('chapters', Heroicon::OutlinedBookOpen, [Toggle::make('index')->label('Show the chapter index')->default(true), Repeater::make('items')->schema([
                TextInput::make('letter')->required()->maxLength(2), TextInput::make('title')->required(), TextInput::make('anchor'), TextInput::make('plan')->default('All plans'),
                Textarea::make('body')->rows(3)->columnSpan(2), Textarea::make('spec')->label('Spec lines (one per line)')->rows(4)->columnSpan(2), TextInput::make('doc_url')->label('Docs link')->columnSpan(2),
            ])->columns(4)->collapsible()->itemLabel(fn (array $state) => trim(($state['letter'] ?? '').' · '.($state['title'] ?? '')))]),
            $block('input_types', Heroicon::OutlinedCursorArrowRays, [$title('Input types, all eleven'), TextInput::make('label'), Repeater::make('items')->schema([TextInput::make('glyph')->maxLength(4), TextInput::make('name')->required(), TextInput::make('desc')])->columns(3), Grid::make(2)->schema([TextInput::make('extra_title'), TextInput::make('extra_body')])]),
            $block('tolerances', Heroicon::OutlinedShieldCheck, [$title('Tolerances'), TextInput::make('label'), Repeater::make('items')->schema([TextInput::make('code')->maxLength(4), TextInput::make('title')->required(), Textarea::make('body')->rows(2)])->columns(3)]),
            $block('drawn_for', Heroicon::OutlinedUserGroup, [$title('Drawn for'), Repeater::make('items')->schema([TextInput::make('code'), TextInput::make('title')->required(), TextInput::make('desc')])->columns(3)->maxItems(6)]),
            $block('team', Heroicon::OutlinedUsers, [$title('The drafting room'), Grid::make(2)->schema([TextInput::make('link_label'), TextInput::make('link_url')]), Repeater::make('members')->schema([TextInput::make('name')->required(), TextInput::make('role'), TextInput::make('photo')->label('Photo URL')->url()])->columns(3)]),
            $block('contact', Heroicon::OutlinedEnvelope, [$sheet(), $title('Send us a *request.*'), Textarea::make('intro')->rows(3), TextInput::make('success'), Repeater::make('channels')->schema([TextInput::make('k')->label('Label')->required(), TextInput::make('v')->label('Value')->required()])->columns(2)]),
            $block('beta', Heroicon::OutlinedBeaker, [$sheet(), $title('Help us draw *version one.*'), Textarea::make('intro')->rows(3), TextInput::make('success'), Repeater::make('timeline')->schema([TextInput::make('title')->required(), TextInput::make('desc')])->columns(2)->maxItems(6)]),
            $block('gets', Heroicon::OutlinedGift, [$title('What beta stores get'), Repeater::make('items')->schema([TextInput::make('code'), TextInput::make('title')->required(), Textarea::make('body')->rows(2)])->columns(3)->maxItems(6)]),
            $block('notes', Heroicon::OutlinedQuestionMarkCircle, [$title('Notes'), Grid::make(2)->schema([Select::make('placement')->options(PageSections::FAQ_PLACEMENTS)->default('home')->required(), TextInput::make('prefix')->default('N')->maxLength(2)]), Placeholder::make('info')->content('Questions come from Website → FAQs with this placement.')]),
            $block('changelog', Heroicon::OutlinedClock, [TextInput::make('count')->numeric()->default(30), Placeholder::make('info')->content('Entries come from Website → Changelog.')]),
            $block('status', Heroicon::OutlinedSignal, [$sheet(), Placeholder::make('info')->content('Components and incidents come from Status page.')]),
            $block('rich_text', Heroicon::OutlinedBars3BottomLeft, [TextInput::make('title'), MarkdownEditor::make('body')->required()]),
            $block('newsletter_band', Heroicon::OutlinedAtSymbol, [$title('New notes, once a month.'), TextInput::make('source')->default('page')]),
            $block('cta_band', Heroicon::OutlinedMegaphone, [$title(), Grid::make(3)->schema([Select::make('style')->options(['orange' => 'Orange', 'dark' => 'Dark'])->default('orange'), Select::make('size')->options(['l' => 'Large', 'xl' => 'Extra large'])->default('l')]), Grid::make(2)->schema([TextInput::make('button_label'), TextInput::make('button_url'), TextInput::make('secondary_label'), TextInput::make('secondary_url')])]),
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
