<?php

namespace Ahs12\Setanjo\Support;

use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Whether a scope's key can be stored in an id column at all.
 *
 * The default morph columns are integers. A UUID or ULID key can never match a row there, and
 * Postgres rejects the comparison outright ("invalid input syntax for type bigint"), so lookups
 * for such keys are skipped instead of sent to the database. Apps that store overrides for
 * UUID/ULID models give the columns a matching type (see the README).
 */
final class ScopeKey
{
    private const INTEGER_TYPES = ['integer', 'int', 'bigint', 'smallint', 'tinyint', 'mediumint', 'int2', 'int4', 'int8', 'serial', 'bigserial'];

    /** @var array<string, bool> column => is an integer column */
    private static array $integerColumns = [];

    public static function fits(Model $model, string $column, mixed $key): bool
    {
        if ($key === null || is_int($key) || ctype_digit((string) $key)) {
            return true;
        }

        $id = $model->getConnectionName().'|'.$model->getTable().'|'.$column;

        return ! (self::$integerColumns[$id] ??= self::isInteger($model, $column));
    }

    public static function flush(): void
    {
        self::$integerColumns = [];
    }

    private static function isInteger(Model $model, string $column): bool
    {
        try {
            $type = strtolower($model->getConnection()->getSchemaBuilder()->getColumnType($model->getTable(), $column));
        } catch (Throwable) {
            return false; // unknown: let the database decide
        }

        return in_array($type, self::INTEGER_TYPES, true);
    }
}
