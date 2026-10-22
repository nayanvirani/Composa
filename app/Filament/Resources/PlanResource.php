<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\PlanResource\Pages;
use App\Models\Plan;
use App\Support\Audit;
use App\Support\PlanCatalog;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Plans. Name, price and trial come from Shopify App Pricing (Partner Dashboard) and are shown
 * read-only; Composa only controls the limits and features each plan unlocks.
 */
class PlanResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'manage_plans';

    protected static ?string $model = Plan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Merchants';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false; // plans are created in the Partner Dashboard first, then seeded with a matching handle
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public const LIMITS = PlanCatalog::LIMIT_LABELS;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('From Shopify App Pricing')->description('Change prices and trials in the Partner Dashboard; they sync here.')->columnSpan(1)->schema([
                TextEntry::make('handle'),
                TextEntry::make('price')->state(fn (?Plan $record) => $record?->priceLabel()),
                TextEntry::make('trial_days')->suffix(' days'),
                TextEntry::make('synced_at')->dateTime()->placeholder('Not synced yet'),
            ]),
            Section::make('Display')->columnSpan(2)->columns(2)->schema([
                TextInput::make('name')->required(),
                TextInput::make('tagline'),
                Toggle::make('is_public')->label('Shown on the website'),
                Toggle::make('is_featured')->label('Highlighted'),
                Toggle::make('dev_only')->label('Development stores only'),
                TextInput::make('sort')->numeric(),
            ]),
            Section::make('Limits')->description('Empty = no limit. Merchants are warned at 80% and new items pause at 100%.')->columnSpanFull()->columns(4)->schema(
                collect(self::LIMITS)->map(fn ($label, $key) => TextInput::make('limits.'.$key)->label($label)->numeric()->minValue(0)
                    ->dehydrateStateUsing(fn ($state) => $state === null || $state === '' ? null : (int) $state))->values()->all()
            ),
            Section::make('Features')->columnSpanFull()->schema([
                Grid::make(3)->schema([
                    Select::make('features.rules_level')->label('Rules')->options(['basic' => 'Basic (show/hide)', 'advanced' => 'Advanced (all actions, AND/OR groups)'])->required(),
                    Select::make('features.analytics')->label('Analytics')->options(['none' => 'None', 'basic' => 'Basic', 'full' => 'Full funnel', 'export' => 'Full + CSV export'])->required(),
                    TextInput::make('features.support')->label('Support')->required(),
                ]),
                CheckboxList::make('features.pricing_types')->label('Pricing types')->options(array_combine(PlanCatalog::PRICING_TYPES, PlanCatalog::PRICING_TYPES))->columns(7),
                Grid::make(4)->schema([
                    Toggle::make('features.nested_steps')->label('Nested & repeatable steps'),
                    Toggle::make('features.uploads')->label('File uploads'),
                    Toggle::make('features.pdf')->label('Production PDF'),
                    Toggle::make('features.pdf_branding')->label('PDF with own branding'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort')
            ->columns([
                TextColumn::make('name')->description(fn (Plan $p) => $p->handle),
                TextColumn::make('price')->state(fn (Plan $p) => $p->priceLabel()),
                TextColumn::make('limits.configurators')->label('Published')->placeholder('∞'),
                TextColumn::make('limits.options')->label('Options')->placeholder('∞'),
                TextColumn::make('limits.rules')->label('Rules')->placeholder('∞'),
                TextColumn::make('limits.products')->label('Products')->placeholder('∞'),
                TextColumn::make('shops_count')->counts(['shops' => fn ($q) => $q->where('status', 'active')])->label('Active shops'),
                IconColumn::make('is_public')->boolean()->label('Public'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlans::route('/'),
            'edit' => Pages\EditPlan::route('/{record}/edit'),
        ];
    }

    public static function audit(Plan $plan): void
    {
        Audit::log('plan.updated', 'Updated limits/features of '.$plan->name, $plan, meta: ['changes' => array_keys($plan->getChanges())]);
    }
}
