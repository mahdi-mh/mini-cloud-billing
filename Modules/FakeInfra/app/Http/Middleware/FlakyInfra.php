<?php

namespace Modules\FakeInfra\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes the fake infrastructure unreliable and dishonest:
 *  - fail_rate: answer 503 and do nothing
 *  - lie_rate:  do the work, then answer 500 anyway (only for calls that change something)
 */
class FlakyInfra
{
    public function handle(Request $request, Closure $next): Response
    {
        $roll = random_int(0, 9999) / 10000;
        $fail = (float) config('fakeinfra.fail_rate');
        $lie = (float) config('fakeinfra.lie_rate');

        if ($roll < $fail) {
            return response()->json(['error' => 'infrastructure unavailable'], 503);
        }

        $response = $next($request);

        if (! $request->isMethodSafe() && $roll < $fail + $lie) {
            return response()->json(['error' => 'internal error'], 500);
        }

        return $response;
    }
}
