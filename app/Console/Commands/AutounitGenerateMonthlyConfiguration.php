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
            $monthTotalDays = date('t');
            $totalTips = $siteWithDefaultConfiguration->tipsPerDay * $monthTotalDays - $siteWithDefaultConfiguration->draw;
            $winRate = rand($siteWithDefaultConfiguration->minWinrate, $siteWithDefaultConfiguration->maxWinrate);

            $siteWithDefaultConfiguration->win = round(($totalTips * ($winRate / 100)));
            $siteWithDefaultConfiguration->loss = round($totalTips * ((100 - $winRate) / 100));
            $siteWithDefaultConfiguration->date = gmdate("Y-m");
            $siteWithDefaultConfiguration->winrate = $winRate;
            $siteWithDefaultConfiguration->leagues = json_encode(League::getDefaultConfigurationLeagues($siteWithDefaultConfiguration->siteId));
            $response = DailySchedule::saveMonthlyConfiguration($siteWithDefaultConfiguration);
            var_dump($response);
        }
    }
}