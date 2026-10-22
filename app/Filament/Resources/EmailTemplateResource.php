<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\EmailTemplateResource\Pages;
use App\Models\EmailTemplate;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Transactional emails (welcome, trial ending, limit reached, ticket replies…). */
class EmailTemplateResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'edit_cms';

    protected static ?string $model = EmailTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Emails';

    protected static ?int $navigationSort = 9;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make()->columnSpan(2)->schema([
                TextInput::make('subject')->required(),
                MarkdownEditor::make('body')->required()->helperText('Use tokens like {{shop_name}} — listed on the right.'),
            ]),
            Section::make()->columnSpan(1)->schema([
                TextInput::make('name')->required(),
                TextInput::make('key')->required()->unique(ignoreRecord: true)->disabledOn('edit'),
                Textarea::make('description')->rows(2),
                TagsInput::make('tokens'),
                Toggle::make('active')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->description(fn (EmailTemplate $r) => $r->description),
            TextColumn::make('subject')->limit(50),
            IconColumn::make('active')->boolean(),
            TextColumn::make('updated_at')->since(),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailTemplates::route('/'),
            'create' => Pages\CreateEmailTemplate::route('/create'),
            'edit' => Pages\EditEmailTemplate::route('/{record}/edit'),
        ];
    }
}
