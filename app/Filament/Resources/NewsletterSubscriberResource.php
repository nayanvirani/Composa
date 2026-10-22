<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\NewsletterSubscriberResource\Pages;
use App\Models\NewsletterSubscriber;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class NewsletterSubscriberResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'edit_cms';

    protected static ?string $model = NewsletterSubscriber::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAtSymbol;

    protected static string|UnitEnum|null $navigationGroup = 'Support';

    protected static ?string $navigationLabel = 'Newsletter';

    protected static ?int $navigationSort = 5;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')
            ->columns([TextColumn::make('email')->searchable()->copyable(), TextColumn::make('source')->badge()->color('gray'), TextColumn::make('created_at')->since()])
            ->headerActions([
                Action::make('export')->label('Export CSV')->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(fn () => response()->streamDownload(function () {
                        $out = fopen('php://output', 'w');
                        fputcsv($out, ['email', 'source', 'subscribed_at']);
                        NewsletterSubscriber::query()->orderBy('id')->each(fn ($s) => fputcsv($out, [$s->email, $s->source, $s->created_at]));
                        fclose($out);
                    }, 'newsletter-subscribers.csv')),
            ])
            ->recordActions([DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageNewsletterSubscribers::route('/')];
    }
}
