<?php

namespace Modules\Servers\Classes;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Servers\Models\Server;

/** The query behind the server list: free text, status, customer and sorting. */
class ServerSearch
{
    public const SORTABLE = ['id', 'name', 'status', 'requested_at'];

    /** @param array{q?: string, status?: string, customer?: string, sort?: string, dir?: string} $filters */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $q = trim($filters['q'] ?? '');

        return Server::query()
            ->with(['customer', 'currentIpAssignments.ipAddress'])
            ->when($q !== '', fn ($query) => $query->where(function ($query) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';

                $query->where('name', 'like', $like)
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $like))
                    ->orWhereHas('currentIpAssignments.ipAddress', fn ($ip) => $ip->where('address', 'like', $like));
            }))
            ->when(($filters['status'] ?? '') !== '', fn ($query) => $query->where('status', $filters['status']))
            ->when(($filters['customer'] ?? '') !== '', fn ($query) => $query->where('customer_id', $filters['customer']))
            ->orderBy($filters['sort'] ?? 'requested_at', $filters['dir'] ?? 'desc')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
