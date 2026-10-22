<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Support\AdminRoles;
use App\Support\Audit;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Staff accounts and roles. Staff sign in with Google; passwords are optional. */
class UserResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'manage_team';

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'Team';

    protected static ?int $navigationSort = 5;

    public static function canDelete(Model $record): bool
    {
        return false; // deactivate instead, so the audit log keeps the name
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required(),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('password')->password()->revealable()->minLength(12)
                ->dehydrated(fn ($state) => filled($state))
                ->helperText('Optional when Google sign-in is enabled.'),
            Toggle::make('active')->default(true)->inline(false)
                ->disabled(fn (?User $record) => $record?->is(auth()->user())),
            CheckboxList::make('roles')->relationship('roles', 'name')->columns(4)->columnSpanFull()
                ->descriptions(collect(AdminRoles::roles())->map(fn ($perms) => count($perms).' permissions')->all())
                ->disabled(fn (?User $record) => $record?->is(auth()->user()))
                ->helperText(fn (?User $record) => $record?->is(auth()->user()) ? 'You can’t change your own roles.' : null),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable()->description(fn (User $u) => $u->email),
                TextColumn::make('roles.name')->badge(),
                IconColumn::make('active')->boolean(),
                IconColumn::make('google_id')->label('Google')->boolean()->state(fn (User $u) => (bool) $u->google_id),
                TextColumn::make('last_login_at')->since()->placeholder('Never'),
            ])
            ->recordActions([EditAction::make()->after(fn (User $record) => Audit::log('team.updated', $record->email.': '.$record->roles->pluck('name')->join(', ').($record->active ? '' : ' (inactive)'), $record))]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageUsers::route('/')];
    }
}
