<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\ContactSubmissionResource\Pages;
use App\Filament\Support\Fields;
use App\Models\ContactSubmission;
use App\Models\Shop;
use App\Models\SupportTicket;
use App\Shopify\ShopDomain;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Website contact form. Each message can be turned into a support ticket. */
class ContactSubmissionResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'manage_support';

    protected static ?string $model = ContactSubmission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Support';

    protected static ?string $navigationLabel = 'Contact form';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('topic')->badge(),
            TextEntry::make('status')->badge(),
            TextEntry::make('name'),
            TextEntry::make('email')->copyable(),
            TextEntry::make('store')->placeholder('—'),
            TextEntry::make('extra')->placeholder('—'),
            TextEntry::make('message')->columnSpanFull(),
            TextEntry::make('ticket_ref')->label('Ticket')->placeholder('—'),
            TextEntry::make('created_at')->dateTime(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->description(fn (ContactSubmission $c) => $c->email),
                TextColumn::make('topic')->badge()->color('gray'),
                TextColumn::make('message')->limit(60),
                Fields::statusColumn(),
                TextColumn::make('created_at')->since(),
            ])
            ->filters([SelectFilter::make('status')->options(['new' => 'New', 'ticket' => 'Ticket created', 'closed' => 'Closed'])])
            ->recordActions([
                ViewAction::make(),
                Action::make('ticket')->label('Create ticket')->icon(Heroicon::OutlinedLifebuoy)
                    ->visible(fn (ContactSubmission $c) => ! $c->ticket_ref)
                    ->action(function (ContactSubmission $record) {
                        $shop = $record->store ? Shop::query()->where('domain', ShopDomain::normalize($record->store))->first() : null;
                        $ticket = SupportTicket::query()->create([
                            'shop_id' => $shop?->id, 'subject' => ucfirst($record->topic).': '.str($record->message)->limit(60),
                            'requester_email' => $record->email, 'requester_name' => $record->name, 'source' => 'website', 'status' => 'open', 'last_reply_at' => now(),
                        ]);
                        $ticket->messages()->create(['author_type' => 'merchant', 'body' => $record->message]);
                        $record->update(['status' => 'ticket', 'ticket_ref' => '#'.$ticket->id]);

                        return redirect(SupportTicketResource::getUrl('view', ['record' => $ticket]));
                    }),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageContactSubmissions::route('/')];
    }
}
