<?php

namespace App\Filament\Resources\SupportTicketResource\Pages;

use App\Filament\Resources\SupportTicketResource;
use App\Mail\TemplatedMail;
use App\Models\CannedReply;
use App\Models\SupportTicket;
use Filament\Actions\Action;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;

class ViewSupportTicket extends ViewRecord
{
    protected static string $resource = SupportTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reply')->icon(Heroicon::OutlinedChatBubbleLeft)
                ->schema([
                    Select::make('canned')->label('Canned reply')->options(fn () => CannedReply::query()->orderBy('sort')->pluck('title', 'id')->all())
                        ->live()->afterStateUpdated(fn ($state, Set $set) => $set('body', CannedReply::query()->find($state)?->body)),
                    MarkdownEditor::make('body')->required(),
                    Toggle::make('note')->label('Internal note (not sent to the merchant)'),
                    Select::make('status')->label('Then set status')->options(['pending' => 'Waiting on merchant', 'open' => 'Open', 'closed' => 'Closed'])->default('pending'),
                ])
                ->action(function (SupportTicket $record, array $data) {
                    $record->messages()->create(['author_type' => $data['note'] ? 'note' : 'admin', 'user_id' => auth()->id(), 'body' => $data['body']]);
                    $record->update(['status' => $data['status'], 'last_reply_at' => now(), 'assigned_to' => $record->assigned_to ?? auth()->id()]);
                    if (! $data['note'] && $record->requester_email && TemplatedMail::exists('ticket_reply')) {
                        Mail::to($record->requester_email)->queue(new TemplatedMail('ticket_reply', [
                            'name' => $record->requester_name, 'subject' => $record->subject, 'reply' => $data['body'],
                            'agent' => auth()->user()->name, 'ticket' => '#'.$record->id,
                        ]));
                    }
                    Notification::make()->title($data['note'] ? 'Note added' : 'Reply sent')->success()->send();
                }),
            Action::make('assign')->icon(Heroicon::OutlinedUserPlus)->color('gray')
                ->fillForm(fn (SupportTicket $record) => ['assigned_to' => $record->assigned_to, 'priority' => $record->priority])
                ->schema([
                    Select::make('assigned_to')->label('Assignee')->options(fn () => SupportTicketResource::staff()),
                    Select::make('priority')->options(['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'])->required(),
                ])
                ->action(fn (SupportTicket $record, array $data) => $record->update($data)),
            Action::make('close')->icon(Heroicon::OutlinedCheckCircle)->color('success')
                ->visible(fn (SupportTicket $record) => $record->status !== 'closed')
                ->action(fn (SupportTicket $record) => $record->update(['status' => 'closed'])),
        ];
    }
}
