<?php

namespace Modules\Servers\Console;

use Illuminate\Console\Command;
use Modules\Servers\Classes\ServerReconciler;
use Modules\Servers\Exceptions\InfraUnavailable;

class ReconcileServersCommand extends Command
{
    protected $signature = 'servers:reconcile';

    protected $description = 'Find and repair differences between our servers and the infrastructure';

    public function handle(ServerReconciler $reconciler): int
    {
        try {
            $fixed = $reconciler->reconcile();
        } catch (InfraUnavailable $e) {
            // normal for this infra; the next run will do it
            $this->warn('Infra not available: '.$e->getMessage());

            return self::FAILURE;
        }

        foreach ($fixed as $line) {
            $this->line($line);
        }

        $this->info($fixed === [] ? 'No drift.' : count($fixed).' difference(s) repaired.');

        return self::SUCCESS;
    }
}
