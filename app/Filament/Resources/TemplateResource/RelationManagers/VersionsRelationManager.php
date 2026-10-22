<?php

namespace App\Filament\Resources\TemplateResource\RelationManagers;

use App\Engine\DefinitionValidator;
use App\Models\FeatureFlag;
use App\Models\Plan;
use App\Models\TemplateVersion;
use App\Services\ConfiguratorService;
use App\Support\Audit;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Template versions. A version's definition can be edited until it is published; after that it is
 * fixed, and changes go into a new version. Rollout: everyone, chosen plans, or a feature flag.
 */
class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            CodeEditor::make('definition_json')->label('Definition (JSON)')->language(Language::Json)->required()
                ->formatStateUsing(fn (?TemplateVersion $record) => json_encode($record?->definition ?? ConfiguratorService::blankDefinition(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
                ->disabled(fn (?TemplateVersion $record) => (bool) $record?->published_at)
                ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                    $decoded = json_decode((string) $value, true);
                    if (! is_array($decoded)) {
                        $fail('Not valid JSON.');

                        return;
                    }
                    foreach (array_slice(DefinitionValidator::validate($decoded), 0, 3) as $error) {
                        $fail($error['path'].': '.$error['message']);
                    }
                }),
            Textarea::make('changelog')->rows(2),
            Select::make('rollout')->options(['all' => 'Everyone', 'plans' => 'Only some plans', 'flag' => 'Behind a feature flag'])->default('all')->required()->live(),
            CheckboxList::make('rollout_value')->label('Plans')->options(fn () => Plan::query()->orderBy('sort')->pluck('name', 'handle')->all())->columns(4)
                ->visible(fn (Get $get) => $get('rollout') === 'plans'),
            Select::make('rollout_flag')->label('Feature flag')->options(fn () => FeatureFlag::query()->pluck('key', 'key')->all())
                ->formatStateUsing(fn (?TemplateVersion $record) => $record?->rollout === 'flag' ? ($record->rollout_value[0] ?? null) : null)
                ->visible(fn (Get $get) => $get('rollout') === 'flag'),
        ]);
    }

    private static function prepare(array $data, ?TemplateVersion $record = null): array
    {
        if (array_key_exists('definition_json', $data) && ! $record?->published_at) {
            $data['definition'] = ConfiguratorService::normalise(json_decode($data['definition_json'], true));
        }
        if (($data['rollout'] ?? null) === 'flag') {
            $data['rollout_value'] = array_filter([$data['rollout_flag'] ?? null]);
        } elseif (($data['rollout'] ?? null) === 'all') {
            $data['rollout_value'] = null;
        }
        unset($data['definition_json'], $data['rollout_flag']);

        return $data;
    }

    public function table(Table $table): Table
    {
        return $table->defaultSort('version', 'desc')
            ->columns([
                TextColumn::make('version')->prefix('v'),
                TextColumn::make('changelog')->limit(60)->placeholder('—'),
                TextColumn::make('rollout')->badge()->color('gray'),
                TextColumn::make('published_at')->dateTime()->placeholder('Draft'),
                TextColumn::make('live')->state(fn (TemplateVersion $v) => $v->id === $this->getOwnerRecord()->current_version_id ? 'Live' : null)->badge()->color('success'),
            ])
            ->headerActions([
                CreateAction::make()->label('New version')
                    ->fillForm(fn () => ['definition_json' => json_encode($this->getOwnerRecord()->currentVersion?->definition ?? ConfiguratorService::blankDefinition(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'rollout' => 'all'])
                    ->mutateDataUsing(fn (array $data) => self::prepare($data) + ['version' => ((int) $this->getOwnerRecord()->versions()->max('version')) + 1]),
            ])
            ->recordActions([
                EditAction::make()->mutateDataUsing(fn (array $data, TemplateVersion $record) => self::prepare($data, $record)),
                Action::make('publish')->icon(Heroicon::OutlinedRocketLaunch)->color('success')->requiresConfirmation()
                    ->visible(fn (TemplateVersion $v) => auth()->user()?->can('publish_cms') && $v->id !== $this->getOwnerRecord()->current_version_id)
                    ->modalDescription('New configurators created from this template will use this version.')
                    ->action(function (TemplateVersion $record) {
                        $record->forceFill(['published_at' => $record->published_at ?? now()])->save();
                        $template = $this->getOwnerRecord();
                        $template->forceFill(['current_version_id' => $record->id])->save();
                        Audit::log('template.published', $template->name.' v'.$record->version, $template);
                        Notification::make()->title('v'.$record->version.' is live')->success()->send();
                    }),
            ]);
    }
}
