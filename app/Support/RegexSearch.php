<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RegexSearch
{
    public static function patternFrom(string $search): ?string
    {
        $search = trim($search);

        if ($search === '') {
            return null;
        }

        if (str_starts_with($search, 'regex:')) {
            $pattern = trim(substr($search, 6));
        } elseif (strlen($search) >= 2 && str_starts_with($search, '/') && str_ends_with($search, '/')) {
            $pattern = substr($search, 1, -1);
        } else {
            return null;
        }

        return $pattern !== '' && @preg_match('/'.$pattern.'/u', '') !== false ? $pattern : null;
    }

    public static function where(Builder $query, array $columns, string $search): Builder
    {
        $pattern = self::patternFrom($search);

        if (! $pattern || DB::connection()->getDriverName() === 'sqlite') {
            $keyword = trim($search);
            $keyword = $pattern ?: $keyword;

            return $query->where(function (Builder $query) use ($columns, $keyword): void {
                foreach ($columns as $index => $column) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $query->{$method}($column, 'like', "%{$keyword}%");
                }
            });
        }

        return $query->where(function (Builder $query) use ($columns, $pattern): void {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                $query->{$method}(self::expression($column), [self::databasePattern($pattern)]);
            }
        });
    }

    public static function expression(string $column): string
    {
        $driver = DB::connection()->getDriverName();
        $wrapped = self::wrapColumn($column);

        return match ($driver) {
            'pgsql' => "{$wrapped} ~ ?",
            'sqlite' => "{$wrapped} REGEXP ?",
            default => "{$wrapped} REGEXP ?",
        };
    }

    public static function databasePattern(string $pattern): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? $pattern : $pattern;
    }

    private static function wrapColumn(string $column): string
    {
        return DB::connection()->getQueryGrammar()->wrap($column);
    }
}
