<?php 

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use App\Site;
use App\Models\AutoUnit\League;
use App\Models\AutoUnit\DailySchedule;

class AutounitGenerateMonthlyConfiguration extends CronCommand
{
    protected $name = 'autounit:generate-monthly-config';
    protected $description = 'If the autounit has a default monthly configuration it will generate it for this month';

    public function fire()
    {
        $sitesWithDefaultConfiguration = Site::withDefaultConfigurations();
        
        foreach ($sitesWithDefaultConfiguration as $siteWithDefaultConfiguration) {
            $siteWithDefaultConfiguration->date = gmdate("Y-m-d");
            $siteWithDefaultConfiguration->leagues = json_encode(League::getDefaultConfigurationLeagues($siteWithDefaultConfiguration->siteId));
            $response = DailySchedule::saveMonthlyConfiguration($siteWithDefaultConfiguration);
            var_dump($response);
        }
    }
}