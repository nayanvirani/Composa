<?php

namespace App\Filament\Resources\MediaAssetResource\Pages;

use App\Filament\Resources\MediaAssetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Storage;

class ManageMediaAssets extends ManageRecords
{
    protected static string $resource = MediaAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Upload')->mutateDataUsing(function (array $data) {
                $disk = Storage::disk(config('composa.media_disk'));

                return $data + ['disk' => config('composa.media_disk'), 'mime' => $disk->mimeType($data['path']) ?: null, 'size' => $disk->size($data['path'])];
            }),
        ];
    }
}
