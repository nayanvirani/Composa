<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Gates a resource on one AdminRoles permission (declare `protected static string $permission`).
 * Resources that are read-only set `protected static bool $readOnly = true`.
 */
trait RequiresPermission
{
    protected static function allowed(): bool
    {
        return (bool) auth()->user()?->can(static::$permission);
    }

    protected static function writable(): bool
    {
        return static::allowed() && ! (property_exists(static::class, 'readOnly') && static::$readOnly);
    }

    public static function canViewAny(): bool
    {
        return static::allowed();
    }

    public static function canView(Model $record): bool
    {
        return static::allowed();
    }

    public static function canCreate(): bool
    {
        return static::writable();
    }

    public static function canEdit(Model $record): bool
    {
        return static::writable();
    }

    public static function canDelete(Model $record): bool
    {
        return static::writable();
    }

    public static function canDeleteAny(): bool
    {
        return static::writable();
    }
}
