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
        $date = date('Y-m-d', strtotime('-60 day', time()) ); // now - 2 months
        $count = Distribution::where('created_at', '<', $date)
            ->delete();

        $this->info(json_encode([
            "deleted" => $count
        ]));
        return true;
    }
}
