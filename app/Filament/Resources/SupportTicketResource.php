<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\SupportTicketResource\Pages;
use App\Filament\Support\Fields;
use App\Models\SupportTicket;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/** Support inbox: tickets from the merchant app, the website contact form and email. */
class SupportTicketResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'manage_support';

    protected static ?string $model = SupportTicket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static string|UnitEnum|null $navigationGroup = 'Support';

    protected static ?string $navigationLabel = 'Inbox';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'subject';

    public static function getNavigationBadge(): ?string
    {
        $open = SupportTicket::query()->where('status', 'open')->count();

        return $open ? (string) $open : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Conversation')->columnSpan(2)->schema([
                RepeatableEntry::make('messages')->hiddenLabel()->contained(false)->schema([
                    TextEntry::make('author_type')->hiddenLabel()->badge()
                        ->formatStateUsing(fn ($state, $record) => match ($state) {
                            'admin' => 'Reply · '.($record->user?->name ?? 'Staff'), 'note' => 'Internal note · '.($record->user?->name ?? 'Staff'), default => 'Merchant'
                        })
                        ->color(fn ($state) => match ($state) {
                            'admin' => 'success', 'note' => 'warning', default => 'gray'
                        }),
                    TextEntry::make('body')->hiddenLabel()->markdown(),
                    TextEntry::make('created_at')->hiddenLabel()->since()->color('gray')->size('sm'),
                ]),
            ]),
            Section::make('Details')->columnSpan(1)->schema([
                TextEntry::make('status')->badge(),
                TextEntry::make('priority')->badge()->color(fn ($state) => in_array($state, ['high', 'urgent'], true) ? 'danger' : 'gray'),
                TextEntry::make('shop.domain')->label('Shop')->placeholder('—')
                    ->url(fn (SupportTicket $t) => $t->shop_id ? ShopResource::getUrl('view', ['record' => $t->shop_id]) : null),
                TextEntry::make('shop.plan.name')->label('Plan')->placeholder('—'),
                TextEntry::make('requester_name')->placeholder('—'),
                TextEntry::make('requester_email')->copyable(),
                TextEntry::make('source')->badge()->color('gray'),
                TextEntry::make('tag')->placeholder('—'),
                TextEntry::make('assignee.name')->label('Assigned to')->placeholder('Unassigned'),
                TextEntry::make('created_at')->dateTime(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_reply_at', 'desc')
            ->modifyQueryUsing(fn (Builder $q) => $q->with(['shop.plan', 'assignee']))
            ->columns([
                TextColumn::make('subject')->searchable()->limit(60)->description(fn (SupportTicket $t) => $t->shop?->domain ?? $t->requester_email),
                Fields::statusColumn(),
                TextColumn::make('priority')->badge()->color(fn ($state) => match ($state) {
                    'urgent' => 'danger', 'high' => 'warning', default => 'gray'
                }),
                TextColumn::make('shop.plan.name')->label('Plan')->badge()->color('gray')->placeholder('—'),
                TextColumn::make('source')->color('gray')->toggleable(),
                TextColumn::make('assignee.name')->label('Assigned')->placeholder('—'),
                TextColumn::make('last_reply_at')->label('Last activity')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(['open' => 'Open', 'pending' => 'Waiting on merchant', 'closed' => 'Closed'])->default('open'),
                SelectFilter::make('priority')->options(['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent']),
                SelectFilter::make('source')->options(['app' => 'App', 'website' => 'Website', 'email' => 'Email']),
                TernaryFilter::make('mine')->label('Assigned to me')->queries(true: fn ($q) => $q->where('assigned_to', auth()->id()), false: fn ($q) => $q->whereNull('assigned_to')),
            ])
            ->recordActions([ViewAction::make()])
            ->toolbarActions([
                BulkAction::make('assignToMe')->label('Assign to me')->icon(Heroicon::OutlinedUserPlus)
                    ->action(fn (Collection $records) => $records->each->update(['assigned_to' => auth()->id()])),
                BulkAction::make('close')->label('Close')->icon(Heroicon::OutlinedCheckCircle)->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each->update(['status' => 'closed'])),
            ]);
    }

    /** @return array<int,string> */
    public static function staff(): array
    {
        return User::query()->where('active', true)->permission('manage_support')->orderBy('name')->pluck('name', 'id')->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupportTickets::route('/'),
            'view' => Pages\ViewSupportTicket::route('/{record}'),
        ];
    }
}
