<?php

namespace App\Filament\Resources\ShopResource\Pages;

use App\Filament\Resources\ShopResource;
use App\Jobs\RefreshShopDetails;
use App\Jobs\SyncStorefrontMetafields;
use App\Models\Impersonation;
use App\Models\Shop;
use App\Services\CartTransformSetup;
use App\Support\Audit;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class ViewShop extends ViewRecord
{
    protected static string $resource = ShopResource::class;

    private function can(string $permission): bool
    {
        return (bool) auth()->user()?->can($permission);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->impersonate('read'),
            $this->impersonate('write'),
            Action::make('adminLink')->label('Shopify admin')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                ->url(fn (Shop $record) => 'https://'.$record->domain.'/admin', true),
            ActionGroup::make([
                Action::make('limits')->label('Limit overrides')->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->visible(fn () => $this->can('manage_shops'))
                    ->fillForm(fn (Shop $record) => ['overrides' => $record->limit_overrides ?? []])
                    ->schema(fn (Shop $record) => collect(['configurators', 'drafts', 'steps', 'options', 'rules', 'products', 'storage_mb', 'languages'])
                        ->map(fn ($key) => TextInput::make('overrides.'.$key)->label(str($key)->replace('_', ' ')->title())->numeric()->minValue(0)
                            ->placeholder('Plan: '.($record->plan?->limit($key) ?? '∞'))->helperText('Empty = use the plan limit'))
                        ->push(Textarea::make('reason')->required()->rows(2))
                        ->all())
                    ->action(function (Shop $record, array $data) {
                        $overrides = array_filter($data['overrides'] ?? [], fn ($v) => $v !== null && $v !== '');
                        $record->forceFill(['limit_overrides' => array_map('intval', $overrides) ?: null])->save();
                        Audit::log('shop.limits_overridden', $data['reason'], $record, meta: ['overrides' => $overrides]);
                        Notification::make()->title('Limit overrides saved')->success()->send();
                    }),
                Action::make('cartMode')->label('Cart mode')->icon(Heroicon::OutlinedShoppingCart)
                    ->visible(fn () => $this->can('manage_shops'))
                    ->fillForm(fn (Shop $record) => ['mode' => $record->cart_mode])
                    ->schema([Select::make('mode')->options(['update' => 'Update (Plus / dev store)', 'expand' => 'Expand', 'discount' => 'Discount fallback'])->required()])
                    ->action(function (Shop $record, array $data, CartTransformSetup $setup) {
                        $record->forceFill(['cart_mode' => $data['mode'], 'settings' => array_merge($record->settings ?? [], ['cart_mode_locked' => true])])->save();
                        try {
                            $setup->writeConfig($record);
                            if ($data['mode'] === 'discount') {
                                $setup->ensureDiscountFallback($record);
                            }
                        } catch (\Throwable $e) {
                            report($e);
                            Notification::make()->title('Saved, but Shopify could not be updated: '.$e->getMessage())->warning()->send();
                        }
                        Audit::log('shop.cart_mode_changed', 'Cart mode set to '.$data['mode'], $record);
                    }),
                Action::make('refresh')->label('Refresh from Shopify')->icon(Heroicon::OutlinedArrowPath)
                    ->action(function (Shop $record) {
                        RefreshShopDetails::dispatch($record->id);
                        Notification::make()->title('Refresh queued')->success()->send();
                    }),
                Action::make('resync')->label('Republish storefront data')->icon(Heroicon::OutlinedCloudArrowUp)
                    ->visible(fn () => $this->can('manage_shops'))
                    ->action(function (Shop $record) {
                        SyncStorefrontMetafields::dispatch($record->id);
                        Audit::log('shop.storefront_resynced', null, $record);
                        Notification::make()->title('Storefront sync queued')->success()->send();
                    }),
                Action::make('freeze')->label(fn (Shop $record) => $record->status === 'frozen' ? 'Unfreeze' : 'Freeze')->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                    ->visible(fn (Shop $record) => $this->can('manage_shops') && $record->status !== 'uninstalled')
                    ->requiresConfirmation()
                    ->modalDescription('A frozen shop keeps its data, but its configurators stop loading on the storefront.')
                    ->schema([Textarea::make('reason')->required()])
                    ->action(function (Shop $record, array $data) {
                        $status = $record->status === 'frozen' ? 'active' : 'frozen';
                        $record->forceFill(['status' => $status])->save();
                        SyncStorefrontMetafields::dispatch($record->id);
                        Audit::log('shop.'.($status === 'frozen' ? 'frozen' : 'unfrozen'), $data['reason'], $record);
                    }),
            ])->label('More')->button()->color('gray'),
        ];
    }

    /** Opens the merchant app as this shop in a new tab. Read-only unless "write" is allowed. */
    private function impersonate(string $mode): Action
    {
        return Action::make('impersonate_'.$mode)
            ->label($mode === 'read' ? 'View as merchant' : 'Act as merchant')
            ->icon($mode === 'read' ? Heroicon::OutlinedEye : Heroicon::OutlinedPencilSquare)
            ->color($mode === 'read' ? 'primary' : 'warning')
            ->visible(fn (Shop $record) => $this->can('impersonate_'.$mode) && $record->status === 'active')
            ->modalHeading($mode === 'read' ? 'View the app as this merchant (read-only)' : 'Act as this merchant (changes are saved)')
            ->modalDescription('The session lasts '.config('composa.admin.impersonation_minutes').' minutes and is recorded in the audit log.')
            ->schema([Textarea::make('reason')->label('Reason (ticket number or note)')->required()->maxLength(255)])
            ->action(function (Shop $record, array $data) use ($mode) {
                $token = Str::random(48);
                Impersonation::query()->create([
                    'user_id' => auth()->id(),
                    'shop_id' => $record->id,
                    'mode' => $mode,
                    'reason' => $data['reason'],
                    'token_hash' => hash('sha256', $token),
                    'expires_at' => now()->addMinutes(config('composa.admin.impersonation_minutes')),
                ]);
                Audit::log('shop.impersonated', $data['reason'], $record, meta: ['mode' => $mode]);
                $this->js('window.open('.json_encode(url('/app?impersonate='.$token)).', "_blank")');
            });
    }
}
