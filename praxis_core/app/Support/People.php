<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Staff (users) and clients (client_users) are separate tables. Columns that either side
 * can fill keep the id plus a type ('staff' | 'client'), for example qa_comments.user_id +
 * author_type. joinAuthor() adds the person's name to a query either way.
 */
class People
{
    /**
     * Left-join whoever is behind $idColumn and add first_name / last_name (and
     * <as>_type) to the select. Call it after ->select(...), since it uses addSelect.
     * $extra adds more columns both tables have, as [column => alias].
     */
    public static function joinAuthor(Builder $query, string $idColumn, string $typeColumn, string $as = 'author', array $extra = []): Builder
    {
        $staff = "{$as}_staff";
        $client = "{$as}_client";
        $columns = ['first_name' => 'first_name', 'last_name' => 'last_name'] + $extra;

        $query
            ->leftJoin("users as {$staff}", fn ($j) => $j->on("{$staff}.id", '=', $idColumn)->where($typeColumn, '=', 'staff'))
            ->leftJoin("client_users as {$client}", fn ($j) => $j->on("{$client}.id", '=', $idColumn)->where($typeColumn, '=', 'client'));

        foreach ($columns as $column => $alias) {
            $query->addSelect(DB::raw("COALESCE({$staff}.{$column}, {$client}.{$column}) as {$alias}"));
        }

        return $query->addSelect(DB::raw("{$typeColumn} as {$as}_type"));
    }
}
