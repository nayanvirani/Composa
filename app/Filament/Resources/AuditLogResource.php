<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\CodeEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Every staff action that touches a merchant or the platform (read-only). */
class AuditLogResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'view_audit';

    protected static bool $readOnly = true;

    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'Audit log';

    protected static ?int $navigationSort = 4;

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('created_at')->dateTime(),
            TextEntry::make('user.name')->label('Staff')->placeholder('System'),
            TextEntry::make('action')->badge(),
            TextEntry::make('shop.domain')->label('Shop')->placeholder('—'),
            TextEntry::make('subject_type')->formatStateUsing(fn ($state) => class_basename((string) $state))->placeholder('—'),
            TextEntry::make('subject_id')->placeholder('—'),
            TextEntry::make('summary')->columnSpanFull()->placeholder('—'),
            TextEntry::make('ip')->placeholder('—'),
            CodeEntry::make('meta')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
                TextColumn::make('user.name')->label('Staff')->placeholder('System'),
                TextColumn::make('action')->badge()->color('gray')->searchable(),
                TextColumn::make('summary')->limit(60)->searchable(),
                TextColumn::make('shop.domain')->label('Shop')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('user_id')->label('Staff')->options(fn () => User::query()->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('action')->options(fn () => AuditLog::query()->distinct()->orderBy('action')->pluck('action', 'action')->all()),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAuditLogs::route('/')];
    }
}
