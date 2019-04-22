<?php 

namespace App\Console\Commands;

use App\Distribution;

class RemoveUnusedDistributions extends CronCommand
{
    protected $name = 'distribution:remove-unused';

    public function fire()
    {
        Distribution::removeUnused();
    }
}