<?php

namespace Modules\Servers\Tests\Concerns;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * A scriptable stand-in for the infrastructure, so tests can make it fail and lie on demand.
 */
trait FakesInfra
{
    /** @var array<string, string> token => state */
    protected array $infra = [];

    /** @var list<'fail'|'lie'|'ok'> what the next calls do, one entry per call */
    protected array $infraScript = [];

    protected int $infraCalls = 0;

    protected function fakeInfra(): void
    {
        Http::fake(function (Request $request) {
            $this->infraCalls++;

            $mode = array_shift($this->infraScript) ?? 'ok';

            if ($mode === 'fail') {
                return Http::response(['error' => 'down'], 503);
            }

            $token = basename(parse_url($request->url(), PHP_URL_PATH));
            $response = $this->infraAnswer($request->method(), $token);

            // the lie: it did the work, but says it did not
            return $mode === 'lie' && $request->method() !== 'GET' ? Http::response(['error' => 'boom'], 500) : $response;
        });
    }

    private function infraAnswer(string $method, string $token)
    {
        if ($method === 'GET' && $token === 'servers') {
            return Http::response(['servers' => collect($this->infra)->map(fn ($state, $t) => ['token' => $t, 'state' => $state])->values()->all()]);
        }

        return match ($method) {
            'PUT' => Http::response(['state' => $this->infra[$token] ??= 'creating'], 201),
            'DELETE' => isset($this->infra[$token])
                ? Http::response(['state' => $this->infra[$token] = 'deleted'])
                : Http::response([], 404),
            default => isset($this->infra[$token])
                ? Http::response(['token' => $token, 'state' => $this->infra[$token]])
                : Http::response([], 404),
        };
    }
}
