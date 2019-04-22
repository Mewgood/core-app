<?php

namespace App\Console;

use App\Console\Commands\DistributionPublish;
use App\Console\Commands\DistributionEmailSchedule;
use App\Console\Commands\ImportNewEvents;
use App\Console\Commands\SetResultAndStatus;
use App\Console\Commands\PublishArchives;
use App\Console\Commands\AutoUnitAddEvents;
use App\Console\Commands\SendMail;
use App\Console\Commands\ProcessSubscriptions;
use App\Console\Commands\ResetAutounit;
use App\Console\Commands\AutounitGenerateMonthlyConfiguration;
use App\Console\Commands\RemoveUnusedDistributions;

use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        DistributionPublish::class,
        DistributionEmailSchedule::class,
        ImportNewEvents::class,
        SetResultAndStatus::class,
        PublishArchives::class,
        AutoUnitAddEvents::class,
        SendMail::class,
        ProcessSubscriptions::class,
        ResetAutounit::class,
        AutounitGenerateMonthlyConfiguration::class,
        RemoveUnusedDistributions::class
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $filePath = storage_path('logs/cron.log');

        // process day subscriptions at end of day
        //   - if no event add noTip
        //   - archive subscriptions
        //   - activate waiting subscriptions
        //   - set package section
        $schedule->command("subscription:process")
            ->dailyAt('00:01');

        /*
        $schedule->call(function() {
            new \App\Http\Controllers\Cron\ProcessDaysSubscription();
        })->timezone('GMT')->dailyAt('00:01');
        */

        // schedule the emails
        $schedule->command('distribution:pre-send')
            ->everyMinute()
            ->appendOutputTo($filePath);
        
        // send every scheduled emails
        $schedule->command('email:send')
            ->everyMinute()
            ->appendOutputTo($filePath);
            
        $schedule->command('publish:archives')
            ->everyMinute()
            ->appendOutputTo($filePath);
        
        // autogenerate autounit at the start of a month
        $schedule->command('autounit:generate-monthly-config')
            ->monthly()
            ->appendOutputTo($filePath);
            
        $schedule->command("distribution:remove-unused")
            ->dailyAt("00:01");
    }
}
