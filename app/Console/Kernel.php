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
use App\Console\Commands\DistributionKeepLastMonth;
use App\Console\Commands\RemoveAutounitLogs;

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
        RemoveUnusedDistributions::class,
        DistributionKeepLastMonth::class,
        RemoveAutounitLogs::class
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

        $schedule->command("distribution:keep-last-month")
            ->dailyAt("00:01");

        $schedule->command("logs:remove-autounit")
            ->dailyAt("00:01");

        // send events to archive
        $schedule->command("publish:distribution")
            ->everyFiveMinutes();

        // mark available events sent in distribution
        $schedule->command('distribution:pre-send')
            ->everyMinute();

        // new events from portal
        $schedule->command("events:import-new")
            ->everyFiveMinutes();

        // get results from feed
        $schedule->command("events:set-result")
            ->appendOutputTo($filePath)
            ->everyFiveMinutes();

        $schedule->command("publish:archives")
            ->everyMinute();

        $schedule->command("autounit:add-events")
            ->dailyAt("12:00");
    }
}
