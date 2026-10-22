<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\MediaAssetResource\Pages;
use App\Models\MediaAsset;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

/** Images for the website: upload here, then paste the URL into pages and posts. */
class MediaAssetResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'edit_cms';

    protected static ?string $model = MediaAsset::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Media';

    protected static ?int $navigationSort = 11;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            FileUpload::make('path')->label('File')->disk(config('composa.media_disk'))->directory('media')->visibility('public')
                ->image()->maxSize(5120)->required()->disabledOn('edit'),
            TextInput::make('alt')->label('Alt text')->maxLength(255),
        ]);
    }

    public static function url(MediaAsset $asset): string
    {
        return Storage::disk($asset->disk)->url($asset->path);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('path')->label('')->disk(config('composa.media_disk'))->square(),
                TextColumn::make('alt')->placeholder('No alt text')->searchable(),
                TextColumn::make('size')->formatStateUsing(fn ($state) => $state ? round($state / 1024).' KB' : '—'),
                TextColumn::make('created_at')->since(),
            ])
            ->recordActions([
                Action::make('copy')->label('Copy URL')->icon(Heroicon::OutlinedLink)
                    ->action(fn (MediaAsset $record, $livewire) => $livewire->js('navigator.clipboard.writeText('.json_encode(self::url($record)).')')),
                EditAction::make(),
                DeleteAction::make()->after(fn (MediaAsset $record) => Storage::disk($record->disk)->delete($record->path)),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageMediaAssets::route('/')];
    }
}
