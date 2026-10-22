<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\TemplateResource\Pages;
use App\Filament\Resources\TemplateResource\RelationManagers\VersionsRelationManager;
use App\Filament\Support\Fields;
use App\Models\Plan;
use App\Models\Template;
use BackedEnum;
use Filament\Actions\EditAction;
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

/** Global starter templates merchants pick from. Definitions are versioned with staged rollout. */
class TemplateResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'edit_cms';

    protected static ?string $model = Template::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public const CATEGORIES = ['jewelry' => 'Jewelry', 'apparel' => 'Apparel', 'furniture' => 'Furniture', 'print' => 'Print & signage', 'gifts' => 'Gifts', 'b2b' => 'B2B', 'other' => 'Other'];

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make()->columnSpan(2)->columns(2)->schema([
                TextInput::make('name')->required(),
                TextInput::make('key')->required()->unique(ignoreRecord: true)->alphaDash()->disabledOn('edit'),
                Select::make('category')->options(self::CATEGORIES)->required(),
                Select::make('min_plan')->label('Needs plan')->options(fn () => Plan::query()->orderBy('sort')->pluck('name', 'handle')->all())->placeholder('Any plan'),
                Textarea::make('description')->rows(3)->columnSpanFull(),
                TextInput::make('cover_image')->label('Cover image URL')->url()->columnSpanFull(),
            ]),
            Section::make('Publishing')->columnSpan(1)->schema([
                Fields::status(['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived']),
                Toggle::make('featured'),
                Fields::sort(),
                Select::make('current_version_id')->label('Live version')
                    ->options(fn (?Template $record) => $record ? $record->versions()->orderByDesc('version')->get()->mapWithKeys(fn ($v) => [$v->id => 'v'.$v->version.($v->published_at ? '' : ' (unpublished)')])->all() : [])
                    ->visibleOn('edit')
                    ->disabled(fn () => ! auth()->user()?->can('publish_cms'))
                    ->helperText('Existing configurators keep the version they were created from.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort')->reorderable('sort')
            ->columns([
                TextColumn::make('name')->searchable()->description(fn (Template $t) => $t->key),
                TextColumn::make('category')->badge()->color('gray'),
                TextColumn::make('currentVersion.version')->label('Version')->prefix('v')->placeholder('—'),
                TextColumn::make('min_plan')->label('Needs')->placeholder('Any'),
                IconColumn::make('featured')->boolean(),
                TextColumn::make('install_count')->label('Installs')->sortable(),
                Fields::statusColumn(),
            ])
            ->filters([SelectFilter::make('category')->options(self::CATEGORIES), SelectFilter::make('status')->options(['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'])])
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [VersionsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTemplates::route('/'),
            'create' => Pages\CreateTemplate::route('/create'),
            'edit' => Pages\EditTemplate::route('/{record}/edit'),
        ];
    }
}
