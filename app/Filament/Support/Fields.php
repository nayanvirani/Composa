<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Str;

/** Form fields shared by the CMS resources. */
final class Fields
{
    /** Draft/published; only staff with publish_cms can change it. */
    public static function status(array $options = ['draft' => 'Draft', 'published' => 'Published']): Select
    {
        return Select::make('status')
            ->options($options)
            ->default(array_key_first($options))
            ->required()
            ->disabled(fn () => ! auth()->user()?->can('publish_cms'))
            ->dehydrated(fn () => (bool) auth()->user()?->can('publish_cms'))
            ->helperText(fn () => auth()->user()?->can('publish_cms') ? null : 'Publishing needs the "Publish website & templates" permission.');
    }

    /** Title that fills the slug while the slug is still untouched. */
    public static function title(string $name = 'title'): TextInput
    {
        return TextInput::make($name)->required()->maxLength(255)->live(onBlur: true)
            ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state) {
                if (($get('slug') ?? '') === Str::slug((string) $old)) {
                    $set('slug', Str::slug((string) $state));
                }
            });
    }

    public static function slug(string $prefix = '/'): TextInput
    {
        return TextInput::make('slug')->required()->maxLength(255)->prefix($prefix)
            ->rule('regex:/^[a-z0-9]+(?:[-\/][a-z0-9]+)*$/')
            ->helperText('Lowercase letters, numbers and dashes.');
    }

    public static function seo(): Section
    {
        return Section::make('Search & sharing')->collapsed()->schema([
            TextInput::make('seo.title')->label('Meta title')->maxLength(70),
            Textarea::make('seo.description')->label('Meta description')->maxLength(170)->rows(2),
            TextInput::make('seo.image')->label('Share image URL')->url(),
            Select::make('seo.robots')->label('Indexing')->options(['index' => 'Index', 'noindex' => 'Hide from search engines'])->default('index'),
        ]);
    }

    public static function sort(): TextInput
    {
        return TextInput::make('sort')->numeric()->default(0)->minValue(0);
    }

    public static function statusColumn(): TextColumn
    {
        return TextColumn::make('status')->badge()->color(fn (?string $state) => match ($state) {
            'published', 'active', 'operational', 'done', 'accepted', 'closed', 'on' => 'success',
            'draft', 'pending', 'new', 'targeted', 'degraded', 'contacted' => 'warning',
            'archived', 'off' => 'gray',
            'failed', 'outage', 'declined', 'frozen', 'uninstalled' => 'danger',
            default => 'info',
        });
    }
}
