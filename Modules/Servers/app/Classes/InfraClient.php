<?php

namespace Modules\Servers\Classes;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Modules\Servers\Exceptions\InfraUnavailable;

/**
 * Talks to the infrastructure. Every method either answers or throws InfraUnavailable:
 * an exception means "we don't know", never "it didn't happen".
 */
class InfraClient
{
    /** @return string|null creating | running | stopped | deleted, or null when the infra doesn't know the token */
    public function state(string $token): ?string
    {
        $response = $this->request('get', "servers/{$token}", allow404: true);

        return $response?->json('state');
    }

    public function create(string $token, string $name): string
    {
        return $this->request('put', "servers/{$token}", ['name' => $name])->json('state');
    }

    public function delete(string $token): string
    {
        return $this->request('delete', "servers/{$token}")->json('state');
    }

    /** @return array<int, array{token: string, state: string}> */
    public function all(): array
    {
        return $this->request('get', 'servers')->json('servers');
    }

    private function request(string $method, string $path, array $data = [], bool $allow404 = false)
    {
        try {
            $response = $this->http()->{$method}($path, $data);
        } catch (ConnectionException $e) {
            throw new InfraUnavailable($e->getMessage(), previous: $e);
        }

        if ($allow404 && $response->status() === 404) {
            return null;
        }

        try {
            return $response->throw();
        } catch (RequestException $e) {
            throw new InfraUnavailable("Infra answered {$response->status()} for {$method} {$path}", previous: $e);
        }
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('servers.infra_url'), '/').'/')
            ->acceptJson()
            ->connectTimeout(2)
            ->timeout((int) config('servers.infra_timeout'));
    }
}
