<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * List and search pages only accept the parameters they declare:
 * `strict-query:q,status,page`. Anything else is a 422 instead of being quietly ignored.
 */
class StrictQuery
{
    public function handle(Request $request, Closure $next, string ...$allowed): Response
    {
        $unknown = array_diff(array_keys($request->query()), $allowed);

        if ($unknown !== []) {
            abort(422, 'Unknown parameter(s): '.implode(', ', $unknown).'. Allowed: '.implode(', ', $allowed).'.');
        }

        return $next($request);
    }
}
