<?php

namespace Modules\FakeInfra\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\FakeInfra\Models\InfraServer;

/**
 * Every call is keyed by a token the caller picks, so repeating a call is always safe.
 */
class InfraServerController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'servers' => InfraServer::all()->map(fn (InfraServer $s) => $this->present($s->settle()))->values(),
        ]);
    }

    public function show(string $token): JsonResponse
    {
        $server = InfraServer::findOrFail($token);

        return response()->json($this->present($server->settle()));
    }

    /** PUT: create it if it does not exist yet, otherwise just report it. */
    public function store(Request $request, string $token): JsonResponse
    {
        $server = InfraServer::firstOrCreate(['token' => $token], [
            'name' => $request->input('name'),
            'state' => 'creating',
            'ready_at' => now()->addSeconds((int) config('fakeinfra.create_delay')),
        ]);

        return response()->json($this->present($server->settle()), $server->wasRecentlyCreated ? 201 : 200);
    }

    public function stop(string $token): JsonResponse
    {
        $server = InfraServer::findOrFail($token)->settle();

        if ($server->state === 'running') {
            $server->update(['state' => 'stopped']);
        }

        return response()->json($this->present($server));
    }

    /** The record stays as a "deleted" tombstone. */
    public function destroy(string $token): JsonResponse
    {
        $server = InfraServer::findOrFail($token);
        $server->update(['state' => 'deleted']);

        return response()->json($this->present($server));
    }

    private function present(InfraServer $server): array
    {
        return ['token' => $server->token, 'name' => $server->name, 'state' => $server->state];
    }
}
