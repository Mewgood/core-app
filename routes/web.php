<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
 */

use Illuminate\Http\Request;
use Carbon\Carbon;
use Ixudra\Curl\Facades\Curl;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

use Illuminate\Support\Facades\Artisan;


    /* -------------------------------------------------------------------
     * - TESTING -
     * This to test will not remain here.
     ---------------------------------------------------------------------*/

// create sha1
/* $app->get('/generate-pass', function () use ($app) { */
/*     return sha1(''); */
/* }); */

    /* -------------------------------------------------------------------
     * - RESET -
     * Here we can reset application.
     ---------------------------------------------------------------------*/

// reset entire aplication
/* $app->get('/reset', function () use ($app) { */
/*     Artisan::call('migrate:refresh'); */
/*     Artisan::call('db:seed'); */
/*     return "Application was reset!"; */
/* }); */

// import events
$app->get('/import-events', function () use ($app) {
    new \App\Http\Controllers\Cron\PortalNewEvents();
});

    /* -------------------------------------------------------------------
     * - ADMIN -
     * all routes group
     ---------------------------------------------------------------------*/

    /*
     * Login to admin section.
     ---------------------------------------------------------------------*/

// each login will generate a new token
// @param string $email
// @param string $password
// @return array()
$app->post('/admin/login', 'Admin\Login@index');

$app->group(['prefix' => 'api'], function ($app) {
    $app->get('/home-archive-configuration/{token}', 'Admin\ArchiveHomeConfigurationController@getSiteConfiguration');
});

$app->group(['prefix' => 'admin', 'middleware' => 'auth'], function ($app) {

	/* 
	 * Routes for the Leagues controller 
	 */
	
    // run autounit from url.
    $app->get('/autounit', function () use ($app) {
        Artisan::call('autounit:add-events');
        return "Autounit was runn with success!";
    });

    $app->get('/autounit/{site}', function ($site) use ($app) {
        Artisan::call('autounit:add-events', ["--site" => $site]);
        return "Autounit was runn with success!";
    });
    $app->get('/autounit/{site}/{table}', function ($site, $table) use ($app) {
        Artisan::call('autounit:add-events', ["--site" => $site, "--table" => $table]);
        return "Autounit was runn with success!";
    });
    
    // reset autounits
    $app->get('/autounit-reset', function () use ($app) {
        Artisan::call('autounit:reset');
        return "Autounit was reset!";
    });

    $app->get('/autounit-reset/{site}', function ($site) use ($app) {
        Artisan::call('autounit:reset', ["--site" => $site]);
        return "Autounit was reset!";
    });
    $app->get('/autounit-reset/{site}/{table}', function ($site, $table) use ($app) {
        Artisan::call('autounit:reset', ["--site" => $site, "--table" => $table]);
        return "Autounit was reset!";
    });
    
	// Get all Countries 
	$app->get('/leagues/get-all-countries', 'Admin\Leagues@getAllCountries');
	// Get all Leagues for a given country  
	$app->get('/leagues/get-country-leagues/{countryCode}', 'Admin\Leagues@getCountryLeagues');
    // Get all Leagues for a given country list
	$app->post('/leagues/get-country-list-leagues', 'Admin\Leagues@getCountryListLeagues');
	// Get all Teams for a given League  
	$app->get('/leagues/get-league-teams/{league}', 'Admin\Leagues@getLeagueTeams');
	// Get all Teams for a given League but exclude the given team
	$app->get('/leagues/get-league-teams/{league}/{exclude}', 'Admin\Leagues@getLeagueTeams');
	// Import Leagues / Teams from the feed
	$app->get('/leagues/import-teams-from-feed', 'Admin\Leagues@importTeamsFeed');

    /*
     * Country
     ---------------------------------------------------------------------*/

    // @return array()
    $app->get('/country/all', function () use ($app) {
        return \App\Country::all();
    });
			
	// @param string $countryCode
    // @param string $alias
    // @return array();
    $app->post('/country/set-alias/{countryCode}', function (Request $r, $countryCode) use ($app) {
		
		
        $countryAlias = $r->input('alias');
		$countryData = ['country' => $countryAlias];

        // update match
        \App\Match::where('countryCode', $countryCode)->update($countryData);
        // update event
        \App\Event::where('countryCode', $countryCode)->update($countryData);
        // update association
        \App\Association::where('countryCode', $countryCode)->update($countryData);
        // update distribution
        \App\Distribution::where('countryCode', $countryCode)->update($countryData);
        // update archiveHome
        \App\ArchiveHome::where('countryCode', $countryCode)->update($countryData);
        // update archiveBig
        \App\ArchiveBig::where('countryCode', $countryCode)->update($countryData);
        // update subscriptionTipHistory
        \App\SubscriptionTipHistory::where('countryCode', $countryCode)->update($countryData);

        $oldAlias = \App\Models\Country\Alias::where('countryCode', $countryCode)
            ->first();

					
        if ($oldAlias) {
			
			\App\Models\Country\Alias::where('countryCode', $countryCode)->update( ['alias' => $countryAlias ] );
			
            return [
                'type' => 'success',
                'message'  => 'Alias for country was updated with success!',
            ];
        }

        \App\Models\Country\Alias::create([
            'countryCode' => $countryCode,
            'alias' => $countryAlias,
        ]);

        return [
            'type' => 'success',
            'message'  => 'Alias for country was created with success!',
        ];
		/*
		*/

    });

	// @param $countryCode
    // @return string
    $app->get('/country/get-country-alias/{countryCode}', function ($countryCode) use ($app) {
        $alias = \App\Models\Country\Alias::where('countryCode', $countryCode)->first();
        return [
            'countryCode' => $countryCode,
            'alias'  => $alias ? $alias->alias : '',
        ];
    });
	
    /*
     * Team
     ---------------------------------------------------------------------*/

    // @param $countryCode
    // @return array()
    $app->get('/team-country/{countryCode}', function ($countryCode) use ($app) {
        $teams = \App\Models\Team\Country::select('teamId')
            ->where('countryCode', $countryCode)
            ->get();

        foreach ($teams as $team) {
            $t = \App\Team::find($team->teamId);
            $team->name = $t->name;
        }

        return $teams;
    });

    // @param $teamId
    // @return string
    $app->get('/team/alias/get/{teamId}', function ($teamId) use ($app) {
        $alias = \App\Models\Team\Alias::where('teamId', $teamId)->first();
        return [
            'teamId' => $teamId,
            'alias'  => $alias ? $alias->alias : '',
        ];
    });

    // @param integer $teamId
    // @paramstring $alias
    // @return array();
    $app->post('/team/alias/{teamId}', function (Request $r, $teamId) use ($app) {

        $alias = $r->input('alias');

        $updateHome = ['homeTeam' => $alias];
        $updateAway = ['awayTeam' => $alias];

        // update match
        \App\Match::where('homeTeamId', $teamId)->update($updateHome);
        \App\Match::where('awayTeamId', $teamId)->update($updateAway);
        // update event
        \App\Event::where('homeTeamId', $teamId)->update($updateHome);
        \App\Event::where('awayTeamId', $teamId)->update($updateAway);
        // update association
        \App\Association::where('homeTeamId', $teamId)->update($updateHome);
        \App\Association::where('awayTeamId', $teamId)->update($updateAway);
        // update distribution
        \App\Distribution::where('homeTeamId', $teamId)->update($updateHome);
        \App\Distribution::where('awayTeamId', $teamId)->update($updateAway);
        // update archiveHome
        \App\ArchiveHome::where('homeTeamId', $teamId)->update($updateHome);
        \App\ArchiveHome::where('awayTeamId', $teamId)->update($updateAway);
        // update archiveBig
        \App\ArchiveBig::where('homeTeamId', $teamId)->update($updateHome);
        \App\ArchiveBig::where('awayTeamId', $teamId)->update($updateAway);
        // update subscriptionTipHistory
        \App\SubscriptionTipHistory::where('homeTeamId', $teamId)->update($updateHome);
        \App\SubscriptionTipHistory::where('awayTeamId', $teamId)->update($updateAway);

        $teamAlias = \App\Models\Team\Alias::where('teamId', $teamId)
            ->first();

        if ($teamAlias) {

            $teamAlias->update(['alias' => $alias]);

            return [
                'type' => 'success',
                'message'  => 'Alias for team was updated with success!',
            ];
        }

        \App\Models\Team\Alias::create([
            'teamId' => $teamId,
            'alias' => $alias,
        ]);

        return [
            'type' => 'success',
            'message'  => 'Alias for team was created with success!',
        ];

    });

    /*
     * League
     ---------------------------------------------------------------------*/

    // @param $countryCode
    // @return array()
    $app->get('/league-country/{countryCode}', function ($countryCode) use ($app) {
        $leagues = \App\Models\League\Country::select('leagueId')
            ->where('countryCode', $countryCode)
            ->get();

        foreach ($leagues as $league) {
            $t = \App\League::find($league->leagueId);
            $league->name = $t->name;
        }

        return $leagues;
    });

    // @param $leagueId
    // @return string
    $app->get('/league/alias/get/{leagueId}', function ($leagueId) use ($app) {
        $alias = \App\Models\League\Alias::where('leagueId', $leagueId)->first();
        return [
            'leagueId' => $leagueId,
            'alias'  => $alias ? $alias->alias : '',
        ];
    });

    // @param integer $leagueId
    // @paramstring $alias
    // @return array();
    $app->post('/league/alias/{leagueId}', function (Request $r, $leagueId) use ($app) {

        $leagueAlias = $r->input('alias');
		$leagueData = ['league' => $leagueAlias];

        // update match
        \App\Match::where('leagueId', $leagueId)->update($leagueData);
        // update event
        \App\Event::where('leagueId', $leagueId)->update($leagueData);
        // update association
        \App\Association::where('leagueId', $leagueId)->update($leagueData);
        // update distribution
        \App\Distribution::where('leagueId', $leagueId)->update($leagueData);
        // update archiveHome
        \App\ArchiveHome::where('leagueId', $leagueId)->update($leagueData);
        // update archiveBig
        \App\ArchiveBig::where('leagueId', $leagueId)->update($leagueData);
        // update subscriptionTipHistory
        \App\SubscriptionTipHistory::where('leagueId', $leagueId)->update($leagueData);

        $oldAlias = \App\Models\League\Alias::where('leagueId', $leagueId)
            ->first();

        if ($oldAlias) {
            $oldAlias->update(['alias' => $leagueAlias]);

            return [
                'type' => 'success',
                'message'  => 'Alias for league was updated with success!',
            ];
        }

        \App\Models\League\Alias::create([
            'leagueId' => $leagueId,
            'alias' => $leagueAlias,
        ]);

        return [
            'type' => 'success',
            'message'  => 'Alias for league was created with success!',
        ];

    });

    /*
     * Auto Units
     ---------------------------------------------------------------------*/

    // auto-units
    // @param integer $siteId
    // @param string $tableIdentifier
    // @param string $date
    // @return array()
    $app->get('/auto-unit/get-schedule', function (Request $r) use ($app) {
        $siteId = $r->input('siteId');
        $tableIdentifier = $r->input('tableIdentifier');
        $date = $r->input('date');

        // get distinct tip for database
        $packages = \App\Package::select(
                "package.*",
                "subscription_alerts.id AS alertId"
            )
            ->distinct()
            ->leftJoin("subscription_alerts", "subscription_alerts.package_id", "package.id")
            ->where('siteId', $siteId)
            ->where('tableIdentifier', $tableIdentifier)
            ->groupBy("tipIdentifier")
            ->get();

        // get configuration for each tip
        $data = [];
        $scheduleType = 'default';

        foreach ($packages as $key => $package) {
            $packageNames = \App\Package::where('siteId', $siteId)
                ->where('tableIdentifier', $tableIdentifier)
                ->where('tipIdentifier', $package->tipIdentifier)
                ->get();
            $hasSubscription = \App\Package::join("subscription", "subscription.packageId", "package.id")
                ->where("package.id", "=", $package->id)
                ->where("subscription.status", "!=", "archived")
                ->exists();
            
            $isDefaultConf = false;

            if ($date == 'default') {
                $schedule = \App\Models\AutoUnit\DefaultSetting::where('siteId', $siteId)
                    ->where('tipIdentifier', $package->tipIdentifier)
                    ->first();

                $subquery = \App\Models\AutoUnit\League::select(
                        DB::raw("IF (auto_unit_league.id IS NOT NULL, true, false)")
                    )
                    ->where('siteId', $siteId)
                    ->where('tipIdentifier', $package->tipIdentifier)
                    ->where('type', 'default')
                    ->whereRaw("auto_unit_league.leagueId = league.id")
                    ->limit(1);

                $associatedLeagues =\App\League::select(
                        "league.id",
                        DB::raw("CONCAT(country.name, ': ', league.name) AS name"),
                        DB::raw("(" . $subquery->toSql() . ") AS isAssociated")
                    )
                    ->join("league_country", "league_country.leagueId", "league.id")
                    ->join("country", "country.code", "league_country.countryCode")
                    ->mergeBindings($subquery->getQuery())
                    ->get()
                    ->toArray();

                $isDefaultConf = true;

                // check if already exists leagues
            } else {
                $schedule = \App\Models\AutoUnit\MonthlySetting::where('siteId', $siteId)
                    ->where('tipIdentifier', $package->tipIdentifier)
                    ->where('date', $date)
                    ->first();
                    
                $subquery = \App\Models\AutoUnit\League::select(
                        DB::raw("IF (auto_unit_league.id IS NOT NULL, true, false)")
                    )
                    ->where('siteId', $siteId)
                    ->where('tipIdentifier', $package->tipIdentifier)
                    ->where('type', 'monthly')
                    ->where('date', $date)
                    ->whereRaw("auto_unit_league.leagueId = league.id")
                    ->limit(1);

                $associatedLeagues =\App\League::select(
                        "league.id",
                        DB::raw("CONCAT(country.name, ': ', league.name) AS name"),
                        DB::raw("(" . $subquery->toSql() . ") AS isAssociated")
                    )
                    ->join("league_country", "league_country.leagueId", "league.id")
                    ->join("country", "country.code", "league_country.countryCode")
                    ->mergeBindings($subquery->getQuery())
                    ->get()
                    ->toArray();

                $scheduleType = 'monthly';

                // schedule not exists for selected month
                // get default configuration
                if (! $schedule) {
                    $schedule = \App\Models\AutoUnit\DefaultSetting::where('siteId', $siteId)
                        ->where('tipIdentifier', $package->tipIdentifier)
                        ->first();

                    $subquery = \App\Models\AutoUnit\League::select(
                            DB::raw("IF (auto_unit_league.id IS NOT NULL, true, false)")
                        )
                        ->where('siteId', $siteId)
                        ->where('tipIdentifier', $package->tipIdentifier)
                        ->where('type', 'default')
                        ->whereRaw("auto_unit_league.leagueId = league.id")
                        ->limit(1);
  
                    $associatedLeagues =\App\League::select(
                        "league.id",
                        DB::raw("CONCAT(country.name, ': ', league.name) AS name"),
                        DB::raw("(" . $subquery->toSql() . ") AS isAssociated")
                    )
                    ->join("league_country", "league_country.leagueId", "league.id")
                    ->join("country", "country.code", "league_country.countryCode")
                    ->mergeBindings($subquery->getQuery())
                    ->get()
                    ->toArray();

                    $scheduleType = 'monthly default';
                }
            }

            // if there is default or monthly schedule
            if ($schedule) {
                $schedule->isTips = ($package->subscriptionType == 'tips');
                $schedule->isDays = ($package->subscriptionType == 'days');
                $schedule->isDefaultConf = $isDefaultConf;
                $schedule->predictions = [];
                $schedule->leagues = $associatedLeagues;
                $schedule->tipIdentifier = $package->tipIdentifier;
                $schedule->scheduleType = $scheduleType;
                $schedule->daysInMonth = (int) date('t', strtotime($date . '-01'));

                if ($date != 'default') {
                    if (! $schedule->tipsNumber)
                        $schedule->tipsNumber = rand($schedule->minTips, $schedule->maxTips);

                    if (! $schedule->winrate) {
                        $schedule->winrate = rand($schedule->minWinrate, $schedule->maxWinrate);

                        // that will be rewrited by specific configuration
                        if ($package->subscriptionType == 'days') {
                            $dayInMonth = (int) date('t', strtotime($date . '-01'));
                            $totalEvents = $dayInMonth * $schedule->tipsPerDay;
                        }

                        if ($package->subscriptionType == 'tips') {
                            $dayInMonth = (int) $schedule->tipsNumber;
                            $totalEvents = $dayInMonth;
                        }

                        // this is the specific configuration
                        if($schedule->configType == 'days') {
                            $dayInMonth = (int) date('t', strtotime($date . '-01'));
                            $totalEvents = $dayInMonth * $schedule->tipsPerDay;
                        }

                        if($schedule->configType == 'tips') {
                            $dayInMonth = (int) $schedule->tipsNumber;
                            $totalEvents = $dayInMonth;
                        }

                        if ($dayInMonth > 0) {
                            $totalEvents = $totalEvents - $schedule->draw;

                            $schedule->win = intval(($schedule->winrate/100) * $totalEvents);
                            $schedule->loss = $totalEvents - $schedule->win;
                        }
                    }
                }

                $schedule->paused = $package->paused_autounit;
                $schedule->manual_pause = $package->manual_pause;
                $schedule->hasAlert = $package->alertId ? true : false;
                $schedule->hasSubscription = $hasSubscription;
                $data[$key] = $schedule;
                $data[$key]->packages = $packageNames;

                continue;
            }

            // there is not a schedule default or monthly
            $data[$key] = [
                'isTips'        => ($package->subscriptionType == 'tips'),
                'isDays'        => ($package->subscriptionType == 'days'),
                'isDefaultConf' => $isDefaultConf,
                'predictions'   => [],
                'leagues'       => $associatedLeagues,
                'tipIdentifier' => $package->tipIdentifier,
                'scheduleType'  => $scheduleType,
                'hasSubscription' => $hasSubscription
            ];

            if ($date != 'default')
                $data[$key]['daysInMonth'] = (int) date('t', strtotime($date . '-01'));
        }
        return $data;
    });

    $app->get('/admin-pool/notification', 'Admin\AutoUnitAdminPool@getNotification');
    $app->post('/auto-unit/create-admin-pool', 'Admin\AutoUnitAdminPool@store');
    $app->post('/auto-unit/remove-admin-pool-matches', 'Admin\AutoUnitAdminPool@removeAdminPoolMatches');
    $app->post('/auto-unit/update-fields', 'Admin\AutoUnitDailySchedule@updateFields');
    $app->post('/auto-unit/get-monthly-statistics', 'Admin\AutoUnitDailySchedule@getMonthlyStatistics');
    $app->get('/auto-unit/get-admin-pool/{date}', 'Admin\AutoUnitAdminPool@get');
    $app->get('/auto-unit/sites/statistics', 'Admin\AutoUnitDailySchedule@getAutoUnitSiteStatistics');
    $app->post('/auto-unit/toggle-state', 'Admin\AutoUnitDailySchedule@toggleState');
    $app->post('/auto-unit/toggle-monthly-config', 'Admin\Site@toggleAUMonthlyGenerationState');
    
    // auto-units
    // @param integer $siteId
    // @param string $tableIdentifier
    // @param string $date
    // @param  array all settings for a tip
    // @return array()
    $app->post('/auto-unit/save-tip-settings', 'Admin\AutoUnitDailySchedule@saveMonthlyConfiguration');

    // auto-units
    // @param integer $siteId
    // @param string $tableIdentifier
    // @param string $date
    // @param  array all settings for a tip
    // @return array()
    $app->get('/auto-unit/get-scheduled-events', function (Request $r) use ($app) {
        $siteId = $r->input('siteId');
        $tableIdentifier = $r->input('tableIdentifier');
        $tipIdentifier = $r->input('tipIdentifier');
        $date = $r->input('date');

        $win = 0;
        $loss = 0;
        $draw = 0;
        $postp = 0;
        $vip = 0;

        // get events for archive
        $manuallyAddedEvents = \App\Distribution::select(
                "distribution.*",
                "distribution.id AS distributionId",
                "distribution.isPublish",
                "match.sites_distributed_counter",
                "package.isVip"
            )
            ->join("event", "event.id", "distribution.eventId")
            ->leftJoin("match", "match.id", "event.matchId")
            ->leftJoin("package", function ($query) {
                $query->on("package.siteId", "=", "distribution.siteId");
                $query->on("package.tipIdentifier", "=", "distribution.tipIdentifier");
                $query->on("package.tableIdentifier", "=", "distribution.tableIdentifier");
            })
            ->where('distribution.siteId', $siteId)
            ->where('distribution.systemDate', '>=', $date . '-01')
            ->where('distribution.systemDate', '<=', $date . '-31')
            ->where('distribution.provider', "!=", "autounit")
            ->where('distribution.tableIdentifier', $tableIdentifier)
            ->groupBy("distribution.associationId")
            ->get()
            ->toArray();

        foreach ($manuallyAddedEvents as $k => $v) {
            $manuallyAddedEvents[$k]['isRealUser'] = false;
            $manuallyAddedEvents[$k]['isNoUser']   = true;
            $manuallyAddedEvents[$k]['isAutoUnit'] = false;

            // check if event was for real users
            if (\App\SubscriptionTipHistory::where('eventId', $v['eventId'])->where('siteId', $v['siteId'])->count()) {
                $manuallyAddedEvents[$k]['isRealUser'] = true;
                $manuallyAddedEvents[$k]['isNoUser']   = false;
            }

            // we must move the flag for table type fron association to archive
            if ($manuallyAddedEvents[$k]['isPublish']) {
                $manuallyAddedEvents[$k]['isPosted']    = true;
                $manuallyAddedEvents[$k]['isScheduled'] = false;
            } else {
                $manuallyAddedEvents[$k]['isPosted']    = false;
                $manuallyAddedEvents[$k]['isScheduled'] = true;
            }

            if ($v['statusId'] == 1)
                $win++;

            if ($v['statusId'] == 2)
                $loss++;

            if ($v['statusId'] == 3)
                $draw++;

            if ($v['statusId'] == 4)
                $postp++;
            if ($v["isVip"]) {
                $vip++;
            }
        }

        usort($manuallyAddedEvents, function($a, $b) {
            return strtotime($b['systemDate']) - strtotime($a['systemDate']);
        });

        // get scheduled events
        $scheduledEvents = \App\Models\AutoUnit\DailySchedule::select(
            "match.*",
            "odd.*",
            "auto_unit_monthly_setting.prediction1x2",
            "auto_unit_monthly_setting.predictionOU",
            "auto_unit_monthly_setting.predictionAH",
            "auto_unit_monthly_setting.predictionGG",
            "auto_unit_daily_schedule.*", 
            "auto_unit_daily_schedule.id AS id",
            "package.isVip",
            "distribution.id AS distributionId",
            "distribution.isPublish"
        )
            ->where('auto_unit_daily_schedule.siteId', $siteId)
            ->leftJoin("match", "match.primaryId", "auto_unit_daily_schedule.match_id")
            ->leftJoin("odd", "odd.id", "auto_unit_daily_schedule.odd_id")
            ->leftJoin("event", "event.matchId", "match.id")
            ->leftJoin("distribution", function($query) {
                $query->on("distribution.eventId", "=", "event.id");
                $query->on("distribution.siteId", "=", "auto_unit_daily_schedule.siteId");
                $query->on("distribution.tipIdentifier", "=", "auto_unit_daily_schedule.tipIdentifier");
                $query->on("distribution.tableIdentifier", "=", "auto_unit_daily_schedule.tableIdentifier");
            })
            ->join("auto_unit_monthly_setting", function ($query) {
                $query->on("auto_unit_monthly_setting.siteId", "=", "auto_unit_daily_schedule.siteId");
                $query->on("auto_unit_monthly_setting.date", "=", "auto_unit_daily_schedule.date");
                $query->on("auto_unit_monthly_setting.tipIdentifier", "=", "auto_unit_daily_schedule.tipIdentifier");
                $query->on("auto_unit_monthly_setting.tableIdentifier", "=", "auto_unit_daily_schedule.tableIdentifier");
            })
            ->join("package", function ($query) {
                $query->on("package.siteId", "=", "auto_unit_daily_schedule.siteId");
                $query->on("package.tipIdentifier", "=", "auto_unit_daily_schedule.tipIdentifier");
                $query->on("package.tableIdentifier", "=", "auto_unit_daily_schedule.tableIdentifier");
            })
            ->where('auto_unit_daily_schedule.tableIdentifier', $tableIdentifier)
            ->where('auto_unit_daily_schedule.date', $date)
            ->groupBy("auto_unit_daily_schedule.id")
            ->get()
            ->toArray();

        foreach ($scheduledEvents as $k => $v) {
            $scheduledEvents[$k]['scheduleId'] = $v["id"];
            $scheduledEvents[$k]['prediction1x2'] = $v["prediction1x2"];
            $scheduledEvents[$k]['predictionOU'] = $v["predictionOU"];
            $scheduledEvents[$k]['predictionAH'] = $v["predictionAH"];
            $scheduledEvents[$k]['predictionGG'] = $v["predictionGG"];
            
            $scheduledEvents[$k]['homeTeam'] = $v["homeTeam"] ? $v["homeTeam"] : "?";
            $scheduledEvents[$k]['awayTeam'] = $v["awayTeam"] ? $v["awayTeam"] : "?";
            $scheduledEvents[$k]['league']   = $v["league"] ? $v["league"] : "?";
            $scheduledEvents[$k]['odd']      = $v["odd"] ? $v["odd"] : "?";
            $scheduledEvents[$k]['predictionGroup'] = $v["predictionId"] ? $v["predictionId"] : $v["predictionGroup"];
            $scheduledEvents[$k]['result']      = $v["result"] ? $v["result"] : "?";

            $scheduledEvents[$k]['isRealUser'] = false;
            $scheduledEvents[$k]['isNoUser']   = false;
            $scheduledEvents[$k]['isAutoUnit'] = true;

            
            // we must move the flag for table type fron association to archive
            if ($scheduledEvents[$k]['isPublish']) {
                $scheduledEvents[$k]['isPosted']    = true;
                $scheduledEvents[$k]['isScheduled'] = false;
            } else {
                $scheduledEvents[$k]['isPosted']    = false;
                $scheduledEvents[$k]['isScheduled'] = true;
            }
            $scheduledEvents[$k]['invalidMatches'] = json_decode($v["invalid_matches"]);

            if ($v['statusId'] == 1)
                $win++;

            if ($v['statusId'] == 2)
                $loss++;

            if ($v['statusId'] == 3)
                $draw++;

            if ($v['statusId'] == 4)
                $postp++;
            if ($v["isVip"]) {
                $vip++;
            }
        }

        $allEvents = array_merge($manuallyAddedEvents, $scheduledEvents);

        usort($allEvents, function($a, $b) {
            return strtotime($b['systemDate']) - strtotime($a['systemDate']);
        });

        return [
            'events' => $allEvents,
            'win'    => $win,
            'loss'   => $loss,
            'draw'   => $draw,
            'postp'  => $postp,
            "vip"   => $vip,
            'winrate' => $win > 0 || $loss > 0 ? round(($win * 100) / ($win + $loss),2) : 0,
            'total'  => $win + $loss + $draw + $postp,
        ];
    });

    // auto-units
    // @param array $ids
    // delete events for AutoUnit Schedule
    // @return array()
    $app->post('/auto-unit/delete-event', function (Request $r) use ($app) {
        $ids = $r->input('ids');
        $count = 0;

        foreach ($ids as $id) {
            \App\Models\AutoUnit\DailySchedule::find($id)->delete();
            $count++;
        }

        return [
            'type' => 'success',
            'message'    => "$count events was deleted from AutoUnit Scheduler",
        ];
    });

    // auto-units
    // @param array $date
    // @param array $siteId
    // @param array $tipIdentifier
    // @param array $tableIdentifier
    // @param array $predictionGroup
    // @param array $statusId
    // @param array $systemDate
    // store new event
    // @return array()
    $app->post('/auto-unit/save-new-schedule-event', function (Request $r) use ($app) {
        $data = [
            'siteId' => $r->input('siteId'),
            'date' => $r->input('date'),
            'tipIdentifier' => $r->input('tipIdentifier'),
            'tableIdentifier' => $r->input('tableIdentifier'),
            'predictionGroup' => $r->input('predictionGroup'),
            'statusId' => $r->input('statusId'),
            'status' => 'waiting',
            'info' => json_encode([]),
            'systemDate' => $r->input('systemDate'),
        ];

        $systemDate = $data['systemDate'];
        $date = new \DateTime($systemDate);

        if ($date->format('Y-m-d') != $systemDate)
            return [
                'type'    => 'error',
                'message' => "Invalid date format!",
            ];

        $today = new \DateTime();
        $today->modify('-1 day');
        if ($date->getTimestamp() < $today->getTimestamp())
            return [
                'type'    => 'error',
                'message' => "Date must be equal or greather than today",
            ];

        // only events in selected month
        $monthDate = new \DateTime($data['date'] . '-01');
        if ($date->format('Y-m') != $monthDate->format('Y-m'))
            return [
                'type'    => 'error',
                'message' => "You can create new event only in selected month",
            ];

        // check for empy values
        foreach ($data as $k => $v) {
            if (empty($v))
                return [
                    'type'    => 'error',
                    'message' => "Field: $k can not be empty.",
                ];
        }

        \App\Models\AutoUnit\DailySchedule::create($data);

        return [
            'type' => 'success',
            'message'    => "New event was successful added in monthly scheduler",
        ];
    });

    /*
     * Logs
     ---------------------------------------------------------------------*/

    // get all logs
    // @return array()
    $app->get('/log/all', function () use ($app) {

        $logs = \App\Models\Log::where('status', 1)
            ->orderBy('created_at', 'DESC')
            ->get();

        $warning = [];
        $panic = [];

        foreach ($logs as $log) {
            $log->info = json_decode($log->info);

            if ($log->type == 'panic')
                $panic[] = $log;
            if ($log->type == 'warning')
                $warning[] = $log;
        }

        return [
            'type' => 'success',
            'lastUpdate' => gmdate('Y-m-d H:i:s'),
            'warning' => $warning,
            'countWarning' => count($warning),
            'panic' => $panic,
            'countPanic' => count($panic),
        ];
    });

    // mark a log as solved
    // @param int $id
    // @return array()
    $app->get('/log/mark-solved/{id}', function ($id) use ($app) {

        $log = \App\Models\Log::find($id);
        $log->status = 0;
        $log->save();

        return [
            'type' => 'success',
            'message' => 'Successful solved',
        ];
    });

    /*
     * Archive Home
     ---------------------------------------------------------------------*/

    // Archive Home
    // @param integer $id
    // @return event || null
    $app->get('/archive-home/event/{id}', 'Admin\ArchiveHome@get');

    // Archive Home
    // @param $id - event id,
    // @param $siteId,
    // @param $country,
    // @param $league,
    // @param $stringEventDate,
    // @param $homeTeam,
    // @param $awayTeam,
    // @param $predictionId,
    // @param $statusId,
    // update event in archive home
    // @return array()
    $app->post('/archive-home/update/{id}', 'Admin\ArchiveHome@update');

    // Archive Home
    // @param integer $siteId
    // @param string $table
    // @return array()
    $app->get('/archive-home/table-events', 'Admin\ArchiveHome@index');

    // Archive Home
    // @param array $order
    // This will save modified order for events in archive big
    // @return void
    $app->post('/archive-home/set-order', 'Admin\ArchiveHome@setOrder');

    // Archive Home Configuration
    // @param integer $siteId
    // @param string $tableIdentifier
    // @param integer $eventsNumber
    // @param integer $dateStart
    // This will save configuration (archive home) for each table in each site
    // After save will delete exceded events number
    // @return array
    $app->post('/archive-home/save-configuration', 'Admin\ArchiveHome@saveConfiguration');

    // Archive Home
    // @param $id,
    // toogle show/hide an event from archivHome
    // @return array()
    $app->get('/archive-home/show-hide/{id}', 'Admin\ArchiveHome@toogleShowHide');

    /*
     * Archive Big
     ---------------------------------------------------------------------*/

    // Archive Big
    // @param integer $id
    // @return event || null
    $app->get('/archive-big/event/{id}', 'Admin\ArchiveBig@get');

    // Archive Big
    // @param integer $siteId
    // @param string $table
    // @param string $date
    // @return array()
    $app->get('/archive-big/month-events', 'Admin\ArchiveBig@getMonthEvents');

    // Archive Big
    // get array with available years and month based on archived events.
    // @return array()
    $app->get('/archive-big/available-months', 'Admin\ArchiveBig@getAvailableMounths');

    // Archive Big
    // @param $id,
    // toogle show/hide an event from archiveBig
    // @return array()
    $app->get('/archive-big/show-hide/{id}', 'Admin\ArchiveBig@toogleShowHide');

    // Archive Big
    // @param $id,
    // @param $siteId,
    // @param $predictionId,
    // @param $StatusId,
    // update prediction and status
    // @return array()
    $app->post('/archive-big/update/prediction-and-status/{id}', 'Admin\ArchiveBig@updatePredictionAndStatus');

    // Archive Big
    // @param $siteId,
    // @param $date format: Y-m,
    // set isPublishInSite 1 for all events fron site in selected month
    // @return array()
    $app->post('/archive-big/publish-month', 'Admin\ArchiveBig@publishMonth');

    /*
     * Sites
     ---------------------------------------------------------------------*/

    // get all sites only ids and names
    // TODO it can be confilict with route site/{is}
    $app->get('/site/ids-and-names', 'Admin\Site@getIdsAndNames');

    // get all sites with all proprieties
    $app->get('/site', 'Admin\Site@index');

    // get specific site by id
    $app->get("/site/{id}", 'Admin\Site@get');

    // store new site
    $app->post("/site", 'Admin\Site@store');

    // update a site
    $app->post("/site/update/{id}", 'Admin\Site@update');

    // delete a site
    $app->get("/site/delete/{id}", 'Admin\Site@destroy');

    // get all alvaillable tables(for archives)
    // @return array()
    $app->get('/site/available-table/{siteId}', 'Admin\Site@getAvailableTables');

    // send client (site) request for update his configuration.
    // route for client is hardcore in controller
    //    - /client/client/get-configuration/$clientId
    // @param integer $id
    // @return array()
    $app->get('/site/update-client/{id}', 'Admin\Client\TriggerAction@updateConfiguration');
    
    // changes the site token and overwrites the token settings files in static sites
    // CMS Tokens must be changed manually
    $app->post('/site/reset-token', 'Admin\Client\TriggerAction@resetToken');

    // send client (site) request to update his arvhive big
    // route for client is hardcore in controller
    //    - /client/update-archive-big/$clientId
    // @param integer $id
    // @return array()
    $app->get('/site/update-archive-big/{id}', 'Admin\Client\TriggerAction@updateArchiveBig');

    // send client (site) request to update his arvhive home
    // route for client is hardcore in controller
    //    - /client/update-archive-home/$clientId
    // @param integer $id
    // @return array()
    $app->get('/site/update-archive-home/{id}', 'Admin\Client\TriggerAction@updateArchiveHome');

    /*
     * Customers
     ---------------------------------------------------------------------*/

    // get all customers from a site filtering email
    // @param integer $siteId
    // @param string  $filter
    // @return array()
    $app->get('customer/search/{siteId}/{filter}', 'Admin\Customer@getCustomersByFilter');

    // create new customer associated with a site
    // @param integer $siteId
    // @param string  $name
    // @param string  $email
    // @param string  $activeEmail
    // @return array()
    $app->post('customer/create/{siteId}', 'Admin\Customer@store');

    /*
     * Packages
     ---------------------------------------------------------------------*/

    // getall packages for a specific site
    // with associated predictions
    $app->get('package-site/{id}', 'Admin\Package@getPackagesBySite');

    // get ids and names for all pacckage associated with site
    // @param integer $siteId
    // @return array()
    $app->get('package-by-site/ids-and-names/{siteId}', 'Admin\Package@getPackagesIdsAndNamesBySite');

    // get specific package by id
    $app->get("/package/{id}", 'Admin\Package@get');

    // update a package
    $app->post("/package/update/{id}", 'Admin\Package@update');

    // store new package
    $app->post("/package", 'Admin\Package@store');

    // delete a package
    $app->get("/package/delete/{id}", 'Admin\Package@destroy');
    
    $app->post("/package/clear-alerts", 'Admin\Package@clearAlerts');

    /*
     * Predictions
     ---------------------------------------------------------------------*/

    // get all predictions order by group
    // @return array()
    $app->get("/prediction", 'Admin\Prediction@index');

    // get statusId for event by result
    // @param string eventId
    // @param string result
    // @return array()
    $app->post("/prediction/status-by-result/{eventId}", function(Request $r, $eventId) use ($app) {
        $result = $r->input('result');

        $event = \App\Event::find($eventId);

        $statusByScore = new \App\Src\Prediction\SetStatusByScore($result, $event->predictionId);
        $statusByScore->evaluateStatus();
        $statusId = $statusByScore->getStatus();

        return [
            'type' => $statusId > 0 ? 'success' : 'error',
            'statusId' => $statusId,
        ];
    });

    /*
     * Site Prediction
     ---------------------------------------------------------------------*/

    // get all predictions names for a site
    $app->get('/site-prediction/{siteId}', 'Admin\SitePrediction@index');

    // update or create all predictions names for a site
    $app->post('/site-prediction/update/{siteId}', 'Admin\SitePrediction@storeOrUpdate');

    /*
     * Site Package
     ---------------------------------------------------------------------*/

    // get all packages ids associated with site
    $app->get('/site-package/{siteId}', 'Admin\SitePackage@get');

    // store if not exists a new association site - package
    $app->post('/site-package', 'Admin\SitePackage@storeIfNotExists');

    /*
     * Site Result Status
     ---------------------------------------------------------------------*/

    // get all results name and statuses for a site
    $app->get('/site-result-status/{siteId}', 'Admin\SiteResultStatus@index');

    // update or create all results name and statuses for a site
    $app->post('/site-result-status/update/{siteId}', 'Admin\SiteResultStatus@storeOrUpdate');

    /*
     * Subscription
     ---------------------------------------------------------------------*/

    // Subscription
    // @param integer $packageId
    // @param string  $name
    // @param integer $subscription
    // @param integer $price
    // @param string  $type days | tips
    // @param string  $dateStart (only for "days" format Y-m-d)
    // @param string  $dateEnd   (only for "days" format Y-m-d)
    // @param string  $customerEmail
    // store new subscription automatic detect if is custom or not
    //  - compare values with original package.
    // @return array()
    $app->post('/subscription/create', 'Admin\Subscription@store');

    // Subscription
    // @param integer $id
    // delete a subscription
    // @return array()
    $app->get('/subscription/delete/{id}', 'Admin\Subscription@destroy');

    // Subscription
    // @param int $id
    // get specific subscription by id
    // @return array()
    $app->get('/subscription/{id}', 'Admin\Subscription@get');

    // Subscription
    // get all subscriptions
    // @return array()
    $app->get('/subscription', 'Admin\Subscription@index');

    // Subscription
    // @param int $id
    // @param string $value
    // update subscrription tipsLeft for tips, dateEnd for days
    // @return array()
    $app->post('/subscription/edit/{id}', 'Admin\Subscription@update');

    $app->get('/subscription-notification', 'Admin\Subscription@getNotifications');
    
    /*
     * Package Prediction
     ---------------------------------------------------------------------*/

    // delete all package predictions and create allnew assocaitions
    $app->post('/package-prediction', 'Admin\PackagePrediction@deleteAndStore');

    /*
     * Events
     ---------------------------------------------------------------------*/

    $app->get('/event/all', 'Admin\Event@index');

    // Events
    // @retun object event
    $app->get('/event/by-id/{id}', 'Admin\Event@get');

    // Events
    // @param integer $id
    // @param string  $result
    // @param integer $statusId
    // @retun array()
    $app->post('/event/update-result-status/{id}', function(Request $r, $id) use ($app) {
        $result = $r->input('result');
        $statusId = $r->input('statusId');

        $eventInstance = new \App\Http\Controllers\Admin\Event();
        return $eventInstance->updateResultAndStatus($id, $result, $statusId);
    });

    // Events
    // get all associated events
    // @return array()
    $app->get('/event/associated-events/{date}', 'Admin\Event@getAssociatedEvents');

    // return distinct providers and leagues based on table selection
    $app->get('/event/available-filters-values/{table}', 'Admin\Event@getTablesFiltersValues');

    // return events number or events based on selection: table, provider, league, minOdd, maxOdd
    $app->get('/event/available/number', 'Admin\Event@getNumberOfAvailableEvents');

    // return events based on selection: table, provider, league, minOdd, maxOdd
    $app->get('/event/available', 'Admin\Event@getAvailableEvents');

    // add event from match
    // @param integer $matchId
    // @param string  $predictionId
    // @param string  $odd
    // @return array()
    $app->post('/event/create-from-match', 'Admin\Event@createFromMatch');

	// add event manually
    // @param integer $homeTeamId
    // @param integer $awayTeamId
    // @param integer $leagueId
    // @param integer $countryCode
    // @param integer $eventDate
    // @param string  $predictionId
    // @param string  $odd
    // @param string  $country
    // @param string  $league
    // @param string  $homeTeam
    // @param string  $awayTeam
    // @return array()
    $app->post('/event/create-manually', 'Admin\Event@createManual');
    $app->post('/event/create-manually-bulk', 'Admin\Event@createManualBulk');
	
	
    /*
     * Matches
     ---------------------------------------------------------------------*/

    // get all available matches by search
    // @param string $filter
    // @return array()
    $app->get('/match/filter/{table}/{filter}', 'Admin\Match@getMatchesByFilter');

	// get all available matches by search - with date filter
    // @param string $filter
    // @return array()
    $app->get('/match/filter/{table}/{filter}/{date}', 'Admin\Match@getMatchesByFilter');
    
    $app->get('/match/prediction/odds/{predictionIdentifier}/{matchId}', 'Admin\Match@getMatchPredictionOdd');
	
    // get match by id
    // @param integer $id
    // @return object
    $app->get('/match/{id}', 'Admin\Match@get');
    
    // Get matches for a given league list and a date Y-m-d
    // @param array $leagueIds
    // @params string $date
    // @return array
    $app->post('/matches/get-league-list-matches', 'Admin\Match@getLeagueMatches');

    /*
     * Odd
     ---------------------------------------------------------------------*/

    // get odd value if exist
    // @param string $matchId
    // @param string $leagueId
    // @param string $predictionId
    // @return array()
    $app->post('/odd/get-value', function(Request $r) use ($app) {
        $matchId = $r->input('matchId');
        $leagueId = $r->input('leagueId');
        $predictionId = $r->input('predictionId');
        $oddValue = '';

        $odd = \App\Models\Events\Odd::where('matchId', $matchId)
            ->where('leagueId', $leagueId)
            ->where('predictionId', $predictionId)
            ->first();

        if ($odd)
            $oddValue = $odd->odd;

        return [
            'type'    => 'success',
            'message' => 'success',
            'value'   => $oddValue,
        ];
    });

    /*
     * Associations - 4 tables
     ---------------------------------------------------------------------*/

    // @param string $tableIdentifier : run, ruv, nun, nuv
    // @param string $date format: Y-m-d | 0 | null
    // get all events associated with a table on sellected date
    //     - $data = 0 | null => current date GMT
    // @return object
    $app->get('/association/event/{tableIdentifier}/{date}', 'Admin\Association@index');

    // add no tip to a table
    // @param string $table
    // @param string $systemDate
    // @return array()
    $app->post("/association/no-tip", 'Admin\Association@addNoTip');


    // create new associations
    // @param array() $eventsIds
    // @param string  $table
    // @param string  $systemDate
    // @return array()
    $app->post("/association", 'Admin\Association@store');

    // @param int $id
    // delete an association
    //    - Not Delete distributed association
    $app->get("/association/delete/{id}", 'Admin\Association@destroy');

    // get available packages according to table and event prediction
    // @param string  $table
    // @param integer $associateEventId
    // @return array();
    $app->get('/association/package/available/{table}/{associateEventId}/{date}', 'Admin\Association@getAvailablePackages');

    /*
     * Distribution
     ---------------------------------------------------------------------*/

    // Distribution
    // @param array $ids
    // This will create template for preview-and-send, template will have placeholders.
    // @return array()
    // $app->post('/distribution/preview-and-send/preview', 'Admin\Email\Flow@createPreviewWithPlaceholders');
    $app->post('/distribution/preview-and-send/preview', 'Admin\Email\Flow@createPreviewWithPlaceholdersUpdated');

    // Distribution
    // @param $timeStart format h:mm || hh:mm
    // @param $timeEndformat h:mm || hh:mm
    // will create date schedule, when email will be send.
    // @return array()
    $app->post('/distribution/create-email-schedule', 'Admin\Distribution@createEmailSchedule');

    // Distribution
    // will delete date scheduled for events that not sended by email yet.
    // This wil worl only for today events
    // @return array()
    $app->get('/distribution/delete-email-schedule', 'Admin\Distribution@deleteEmailSchedule');

    // Distribution
    // Will set date when selects events will be sended by email.
    // @return array()
    $app->post('/distribution/set-time-email-schedule', 'Admin\Distribution@setTimeEmailSchedule');

    // Distribution
    // this is use to have a full preview of template with all events included.
    // @param array $ids
    // @return array()
    // $app->post('/distribution/preview-and-send/preview-template', 'Admin\Email\Flow@createFullPreview');
    $app->post('/distribution/preview-and-send/preview-template', 'Admin\Email\Flow@createFullPreviewUpdated');

    // Distribution
    // @param array $ids
    // @param string|null|false $template
    // This will add events to subscriptions, also will move events to email schedule.
    $app->post('/distribution/preview-and-send/send', function (Request $r) use ($app) {
	
		// test the subscription emails cron
		/*
		$info = [
            'scheduled' => 0,
            'message' => []
        ];
		
		$events =  \App\Distribution::where('isEmailSend', '0')
            ->whereNotNull('mailingDate')
            ->where('mailingDate', '<=', gmdate('Y-m-d H:i:s'))
            ->get();
		
		$group = [];
        foreach ($events as $e) {
            $group[$e->packageId][] = $e->id;
        }
		
		foreach ($group as $gids) {
            $distributionInstance = new \App\Http\Controllers\Admin\Distribution();
            // $result = $distributionInstance->associateEventsWithSubscription($gids);
            $result = $distributionInstance->associateEventsWithSubscriptionUpdated($gids);
            $info['message'][] = $result['message'];
            $info['scheduled'] = $info['scheduled'] + count($gids);
        }
		
		return response()->json($info);
		echo "<pre>";die(print_r($info));
		*/
			
        $ids = $r->input('ids');
        $template = $r->input('template');
		
		if( !$ids ){
			$ids = [];
		}

        if (! $template) {
            $group = [];
            $events = \App\Distribution::whereIn('id', $ids)->get();
            foreach ($events as $e) {
                $group[$e->packageId][] = $e->id;
            }

            $message = '';
            foreach ($group as $gids) {
                $distributionInstance = new \App\Http\Controllers\Admin\Distribution();
                // $result = $distributionInstance->associateEventsWithSubscription($gids);
                $result = $distributionInstance->associateEventsWithSubscriptionUpdated($gids);
                $message .= $result['message'];
            }

            return [
                'type' => 'success',
                'message' => $message,
            ];
        } else {
			$group = [];
            $events = \App\Distribution::whereIn('id', $ids)->get();
            foreach ($events as $e) {
                $group[$e->packageId][] = $e->id;
            }

            $message = '';
            foreach ($group as $gids) {
                $distributionInstance = new \App\Http\Controllers\Admin\Distribution();
                // $result = $distributionInstance->associateEventsWithSubscription($gids);
                $result = $distributionInstance->associateEventsWithSubscriptionUpdated($gids, $template);
                $message .= $result['message'];
            }

            return [
                'type' => 'success',
                'message' => $message,
            ];
		}
		
		/*
        $distributionInstance = new \App\Http\Controllers\Admin\Distribution();
        // return $distributionInstance->associateEventsWithSubscription($ids, $template);
        return $distributionInstance->associateEventsWithSubscriptionUpdated($ids, $template);
		*/
    });

    // Distribution
    // manage customer restricted tips
    // this will work only for today
    $app->get('/distribution/subscription-restricted-tips', function () use ($app) {

        $data = [];
        $date = gmdate('Y-m-d');

        // get all packages
        $pack = \App\Package::select('id')->get();

        foreach ($pack as $p) {

            // get package associadet events from distribution
            $events = \App\Distribution::where('packageId', $p->id)
                ->where('systemDate', gmdate('Y-m-d'))->get()->toArray();

            // get all subscriptions for package
            $subscriptionInstance = new \App\Http\Controllers\Admin\Subscription();
            $subscriptonsIds = $subscriptionInstance->getSubscriptionsIdsWithNotEnoughTips($p->id);

            foreach ($subscriptonsIds as $subscriptionId) {

                // get all restricted tips for subscription
                $restrictedTips = \App\SubscriptionRestrictedTip::where('subscriptionId', $subscriptionId)
                    ->where('systemDate', $date)->get()->toArray();

                $e = $events;
                foreach ($e as $k => $v) {
                    // set default to false
                    $e[$k]['restricted'] = false;
                    foreach ($restrictedTips as $r) {
                        if ($v['id'] == $r['distributionId'])
                            $e[$k]['restricted'] = true;
                    }
                }

                $subscription = \App\Subscription::find($subscriptionId);

                $data[$subscription->siteId]['siteName'] = \App\Site::find($subscription->siteId)->name;

                $data[$subscription->siteId]['subscriptions'][] = [
                    'id'               => $subscription->id,
                    'siteName'         => \App\Site::find($subscription->siteId)->name,
                    'subscriptionName' => $subscription->name,
                    'customerId'       => $subscription->customerId,
                    'customerEmail'    => \App\Customer::find($subscription->customerId)->email,
                    'totalTips'        => $subscription->tipsLeft - $subscription->tipsBlocked,
                    'totalEvents'      => count($events),
                    'events'           => $e,
                ];
            }
        }

        return response()->json([
            'type' => 'success',
            'date' => gmdate('Y-m-d'),
            'data' => $data,
        ]);
    });

    // Distribution
    // @param string $systemDate
    // @param array $associations
    // 1 - delete all subscription restricted tips for $systemDate
    // 2 - create subscription restricted tips from $associations
    // @return array()
    $app->post('/distribution/subscription-restricted-tips', function (Request $r) use ($app) {
        $systemDate = $r->input('systemDate');
        $restrictions = $r->input('restrictions');

        // TODO
        // check systemDate to be valid

        // delete all restrictions
        \App\SubscriptionRestrictedTip::where('systemDate', $systemDate)->delete();

        // creeate again restrictions
        foreach ($restrictions as $restriction) {
            \App\SubscriptionRestrictedTip::create([
                'subscriptionId' => $restriction['subscriptionId'],
                'distributionId' => $restriction['distributionId'],
                'systemDate'     => $systemDate,
            ]);
        }

        return response()->json([
            'type'    => 'success',
            'message' => 'Success update Manage Users',
        ]);
    });

    // Distribution
    // @param string $eventId
    // @param array  $packagesIds
    // delete distributions of event - package (if packageId is not in $packagesIds)
    //    - Not Delete events hwo was already published
    // create new associations event - packages
    $app->post("/distribution", 'Admin\Distribution@storeAndDelete');

    // Distribution
    // @string $date format: Y-m-d || 0 || null
    // get all distributed events for specific date.
    // @return array()
    $app->get("/distribution/{date}", 'Admin\Distribution@index');
	
	// Distribution
    // @string $date format: Y-m-d || 0 || null
    // get all distributed events for specific date  - Gropuped by type
    // @return array()
    $app->post("/get-distributions/{date}", 'Admin\Distribution@getDistributionsGrouped');

    // Distribution
    // @param array $ids
    // delete distributed events
    //   - Not Delete events already sended in archives
    $app->post("/distribution/delete", 'Admin\Distribution@destroy');

    // Distribution
    // @param array $ids
    // delete distributed events
    //   - Not Delete events already sended in archives
    $app->post("/distribution/force-delete", 'Admin\Distribution@forceDestroy');

    /*
     * Archive
     ---------------------------------------------------------------------*/

    // get all events for all archives
    $app->get('/archive', function() use ($app) {
        return \App\ArchiveBig::all();
    });

    // publish events in archive
    // @param array $ids (distributionId)
    //  - mark events publish in distribution
    //  - send events in archive
    // @return array()
    $app->post('/archive/publish', function(Request $r) use ($app) {
        $ids = $r->input('ids');
        $archive = new \App\Http\Controllers\Admin\Archive();
        return $archive->publish($ids);
    });

    /*
     * Test
     * Here we collect test routes for imap, smtp and other many types
     ---------------------------------------------------------------------*/

    // Test
    // @param int $siteId
    // @param string $email
    // for test smtp connection will create new record in email_schedule table with a test email
    // @return array()
    $app->post('/test/send-test-email/{siteid}', function (Request $r, $siteId) use ($app) {
        $email = $r->input('email');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            return [
                'type' => 'error',
                'message' => 'You must enter a valid email address',
            ];

        $site = \App\Site::find($siteId);
        if ($site == null)
            return [
                'type' => 'error',
                'message' => "Can not find site with id: $siteId",
            ];

        $args = [
            'provider'        => 'site',
            'sender'          => $site->id,
            'type'            => 'testSmpt',
            'identifierName'  => 'siteId',
            'identifierValue' => $site->id,
            'from'            => $site->email,
            'fromName'        => $site->name,
            'to'              => $email,
            'toName'          => $email,
            'subject'         => 'Test smpt for ' . $site->name,
            'body'            => 'This is a test email to check smpt configuration.',
            'status'          => 'waiting',
        ];
        \App\EmailSchedule::create($args);

        return [
            'type' => 'success',
            'message' => "An emai was scheduled for sendind \n to: $email \n from: $site->name",
        ];
    });

});





















	// Added by GDM - test function
    $app->get('gdm-test/distribution/test-schedule', function (Request $r) use ($app) {
		
		// test the subscription emails cron
		// die(Carbon::now('UTC')->addMinutes(5));
		// die(gmdate('Y-m-d H:i:s'));
		
		$info = [
            'scheduled' => 0,
            'message' => []
        ];
		
		$events =  \App\Distribution::where('isEmailSend', '0')
            ->whereNotNull('mailingDate')
            ->where('mailingDate', '<=', gmdate('Y-m-d H:i:s'))
			->where('eventDate', '>', Carbon::now('UTC')->addMinutes(5))
            ->get();
		
		$group = [];
        foreach ($events as $e) {
            $group[$e->packageId][] = $e->id;
        }
		
		foreach ($group as $gids) {
            $distributionInstance = new \App\Http\Controllers\Admin\Distribution();
            // $result = $distributionInstance->associateEventsWithSubscription($gids);
            $result = $distributionInstance->associateEventsWithSubscriptionUpdated($gids);
            $info['message'][] = $result['message'];
            $info['scheduled'] = $info['scheduled'] + count($gids);
        }
		
		return response()->json($info);
		echo "<pre>";die(print_r($info));
		
	});