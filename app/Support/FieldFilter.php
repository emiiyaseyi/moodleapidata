<?php

namespace App\Support;

use Illuminate\Http\Request;

class FieldFilter
{
    /**
     * Whitelist top-level keys of $data based on a comma-separated
     * "fields" query param (?fields=a,b,c). Returns $data unchanged
     * if no fields param was given, or if $data isn't associative.
     */
    public static function apply(Request $request, mixed $data): mixed
    {
        $fields = $request->query('fields');

        if (! $fields || ! is_array($data)) {
            return $data;
        }

        $wanted = array_filter(array_map('trim', explode(',', $fields)));

        if (empty($wanted)) {
            return $data;
        }

        if (array_is_list($data)) {
            return array_map(fn ($item) => is_array($item) ? array_intersect_key($item, array_flip($wanted)) : $item, $data);
        }

        return array_intersect_key($data, array_flip($wanted));
    }
}
