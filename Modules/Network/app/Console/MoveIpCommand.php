<?php

namespace Modules\Network\Console;

use Illuminate\Console\Command;
use Modules\Network\Exceptions\IpMoveRefused;
use Modules\Network\Models\IpAddress;
use Modules\Network\Services\IpService;
use Modules\Servers\Models\Server;

class MoveIpCommand extends Command
{
    protected $signature = 'ips:move {ip : ip address id} {from : server id it is on} {to : server id to move it to}';

    protected $description = 'Move an IP address from one server to another';

    public function handle(IpService $ips): int
    {
        try {
            $ips->move(
                IpAddress::findOrFail($this->argument('ip')),
                Server::findOrFail($this->argument('from')),
                Server::findOrFail($this->argument('to')),
            );
        } catch (IpMoveRefused $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Moved.');

        return self::SUCCESS;
    }
}
