<?php

namespace App\Filament\Resources\PageResource\RelationManagers;

use App\Models\PageRevision;
use App\Support\Audit;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Page history: every save stores a snapshot; restoring one brings the page back as a draft. */
class RevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'revisions';

    protected static ?string $title = 'History';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Saved')->dateTime()->sortable(),
                TextColumn::make('user.name')->label('By')->placeholder('System'),
                TextColumn::make('snapshot.status')->label('Status')->badge(),
                TextColumn::make('snapshot.title')->label('Title'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('restore')->icon(Heroicon::OutlinedArrowUturnLeft)->requiresConfirmation()
                    ->modalDescription('The page content is replaced with this version and saved as a draft.')
                    ->action(function (PageRevision $record) {
                        $page = $this->getOwnerRecord();
                        $snapshot = $record->snapshot;
                        $page->fill(['title' => $snapshot['title'] ?? $page->title, 'sections' => $snapshot['sections'] ?? [], 'seo' => $snapshot['seo'] ?? null, 'status' => 'draft'])->save();
                        Audit::log('page.restored', 'Restored "'.$page->title.'" to the version from '.$record->created_at->toDayDateTimeString(), $page);
                        $this->redirect(request()->header('Referer') ?? url()->current());
                    }),
            ]);
    }
}
