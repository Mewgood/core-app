<?php 

namespace App\Console\Commands;

use App\Distribution;
use Carbon\Carbon;

class DistributionKeepLastMonth extends CronCommand
{
    protected $name = 'distribution:keep-last-month';
    protected $description = 'Delete all distributions except the last month';

    public function fire()
    {
        $count = Distribution::whereMonth(
            'created_at', '<=', Carbon::now()->subMonth()->month
        )->delete();

        $this->info(json_encode([
            "deleted" => $count
        ]));
        return true;
    }
}
