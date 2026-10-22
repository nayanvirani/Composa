<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use App\Support\Audit;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Brand, contact addresses, links, SEO defaults and analytics for the public website. */
class SiteSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Site settings';

    protected static ?int $navigationSort = 12;

    protected string $view = 'filament.pages.site-settings';

    public const KEYS = ['brand', 'contact', 'links', 'seo', 'analytics', 'company', 'footer'];

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('edit_cms');
    }

    public function mount(): void
    {
        $this->form->fill(collect(self::KEYS)->mapWithKeys(fn ($k) => [$k => SiteSetting::get($k, [])])->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Brand')->columns(2)->schema([
                TextInput::make('brand.name')->default('Composa')->required(),
                TextInput::make('brand.tagline'),
                TextInput::make('brand.logo_url')->label('Logo URL')->url(),
                TextInput::make('brand.favicon_url')->label('Favicon URL')->url(),
            ]),
            Section::make('Contact')->columns(3)->schema([
                TextInput::make('contact.support_email')->email(),
                TextInput::make('contact.sales_email')->email(),
                TextInput::make('contact.privacy_email')->email(),
                TextInput::make('contact.response_time')->placeholder('Replies within one business day'),
            ]),
            Section::make('Links')->columns(2)->schema([
                TextInput::make('links.app_store')->label('Shopify App Store listing')->url(),
                TextInput::make('links.install')->label('Install link (buttons)')->url(),
                TextInput::make('links.x')->label('X')->url(),
                TextInput::make('links.linkedin')->label('LinkedIn')->url(),
                TextInput::make('links.youtube')->label('YouTube')->url(),
                TextInput::make('links.status')->label('Status page')->placeholder('/status'),
            ]),
            Section::make('Search defaults')->columns(2)->schema([
                TextInput::make('seo.title_suffix')->placeholder(' — Composa'),
                TextInput::make('seo.image')->label('Default share image URL')->url(),
                Textarea::make('seo.description')->rows(2)->maxLength(170)->columnSpanFull(),
            ]),
            Section::make('Analytics')->columns(2)->schema([
                TextInput::make('analytics.plausible_domain')->label('Plausible domain'),
                TextInput::make('analytics.ga4_id')->label('GA4 measurement ID')->regex('/^G-[A-Z0-9]+$/'),
                Toggle::make('analytics.cookie_banner')->label('Show cookie notice'),
            ]),
            Section::make('Company')->columns(2)->schema([
                TextInput::make('company.legal_name'),
                TextInput::make('company.registration'),
                Textarea::make('company.address')->rows(2)->columnSpanFull(),
            ]),
            Section::make('Footer')->schema([
                Textarea::make('footer.note')->rows(2)->placeholder('Composa is not affiliated with Shopify Inc.'),
            ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        foreach (self::KEYS as $key) {
            SiteSetting::put($key, $state[$key] ?? []);
        }
        Audit::log('site.settings_updated');
        Notification::make()->title('Settings saved')->success()->send();
    }
}
