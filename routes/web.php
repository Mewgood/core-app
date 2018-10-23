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

// run autounit from url.
$app->get('/autounit', function () use ($app) {
    Artisan::call('autounit:add-events');
    return "Autounit was runn with success!";
});

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
        $tips = \App\Package::distinct()
            ->where('siteId', $siteId)
            ->where('tableIdentifier', $tableIdentifier)
            ->get(['tipIdentifier']);


        // get configuration for each tip
        $data = [];
        $scheduleType = 'default';
        foreach ($tips as $key => $tip) {
            $tipIdentifier = $tip->tipIdentifier;

            $isDefaultConf = false;

            // get package
            $package = \App\Package::where('siteId', $siteId)
                ->where('tipIdentifier', $tipIdentifier)
                ->first();

            // get all leagues from aplication
            $leagues = \App\League::all();

            $country = [];
            foreach (\App\Country::all()->toArray() as $c)
                $country[$c['code']] = $c['name'];

            $leagueCountry = [];
            foreach (\App\Models\League\Country::all()->toArray() as $lc)
                $leagueCountry[$lc['leagueId']] = $lc['countryCode'];

            foreach ($leagues as $l) {
                if (array_key_exists($l->id, $leagueCountry)) {
                    $code = $leagueCountry[$l->id];
                    if (array_key_exists($code, $country))
                        $l->name = $country[$code] . ': ' . $l->name;
                }
            }

            if ($date == 'default') {
                $schedule = \App\Models\AutoUnit\DefaultSetting::where('siteId', $siteId)
                    ->where('tipIdentifier', $tipIdentifier)
                    ->first();

                $associatedLeagues = \App\Models\AutoUnit\League::where('siteId', $siteId)
                    ->where('tipIdentifier', $tipIdentifier)
                    ->where('type', 'default')
                    /* ->where('leagueId', $league->id) */
                    ->get();

                foreach ($leagues as $league) {
                    $league->isAssociated = false;
                    foreach ($associatedLeagues as $assocLeague) {
                        if ($league->id == $assocLeague->leagueId) {
                            $league->isAssociated = true;
                            continue 2;
                        }
                    }
                }

                $isDefaultConf = true;

                // check if already exists leagues
            } else {
                $schedule = \App\Models\AutoUnit\MonthlySetting::where('siteId', $siteId)
                    ->where('tipIdentifier', $tipIdentifier)
                    ->where('date', $date)
                    ->first();

                $associatedLeagues = \App\Models\AutoUnit\League::where('siteId', $siteId)
                    ->where('tipIdentifier', $tipIdentifier)
                    ->where('type', 'monthly')
                    ->where('date', $date)
                    ->get();

                foreach ($leagues as $league) {
                    $league->isAssociated = false;
                    if (count($associatedLeagues) > 0) {
                        foreach ($associatedLeagues as $assocLeague) {
                            if ($league->id == $assocLeague->leagueId) {
                                $league->isAssociated = true;
                                continue 2;
                            }
                        }
                    }
                }

                $scheduleType = 'monthly';

                // schedule not exists for selected month
                // get default configuration
                if (! $schedule) {
                    $schedule = \App\Models\AutoUnit\DefaultSetting::where('siteId', $siteId)
                        ->where('tipIdentifier', $tipIdentifier)
                        ->first();

                    $associatedLeagues = \App\Models\AutoUnit\League::where('siteId', $siteId)
                        ->where('tipIdentifier', $tipIdentifier)
                        ->where('type', 'default')
                        ->get();

                    foreach ($leagues as $league) {
                        $league->isAssociated = false;
                        if (count($associatedLeagues) > 0) {
                            foreach ($associatedLeagues as $assocLeague) {
                                if ($league->id == $assocLeague->leagueId) {
                                    $league->isAssociated = true;
                                    continue 2;
                                }
                            }
                        }
                    }

                    $scheduleType = 'monthly default';
                }
            }

            // if there is default or monthly schedule
            if ($schedule) {
                $schedule->isTips = ($package->subscriptionType == 'tips');
                $schedule->isDays = ($package->subscriptionType == 'days');
                $schedule->isDefaultConf = $isDefaultConf;
                $schedule->predictions = [];
                $schedule->leagues = $leagues;
                $schedule->tipIdentifier = $tipIdentifier;
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

                $data[$key] = $schedule;

                continue;
            }

            // there is not a schedule default or monthly
            $data[$key] = [
                'isTips'        => ($package->subscriptionType == 'tips'),
                'isDays'        => ($package->subscriptionType == 'days'),
                'isDefaultConf' => $isDefaultConf,
                'predictions'   => [],
                'leagues'       => $leagues,
                'tipIdentifier' => $tipIdentifier,
                'scheduleType'  => $scheduleType,
            ];

            if ($date != 'default')
                $data[$key]['daysInMonth'] = (int) date('t', strtotime($date . '-01'));
        }
        return $data;
    });

    $app->post('/auto-unit/create-admin-pool', 'Admin\AutoUnitAdminPool@store');
    $app->get('/auto-unit/get-admin-pool/{date}', 'Admin\AutoUnitAdminPool@get');
    $app->post('/auto-unit/remove-admin-pool-matches', 'Admin\AutoUnitAdminPool@removeAdminPoolMatches');
    
    // auto-units
    // @param integer $siteId
    // @param string $tableIdentifier
    // @param string $date
    // @param  array all settings for a tip
    // @return array()
    $app->post('/auto-unit/save-tip-settings', function (Request $r) use ($app) {
        $siteId = $r->input('siteId');
        $tableIdentifier = $r->input('tableIdentifier');
        $tipIdentifier = $r->input('tipIdentifier');
        $date = $r->input('date');
        $leagues = json_decode($r->input('leagues'));

        $configType = $r->input('configType');
        $minWinrate = $r->input('minWinrate');
        $maxWinrate = $r->input('maxWinrate');
        $tipsPerDay = $r->input('tipsPerDay');
        $tipsNumber = $r->input('tipsNumber');

        // default settings
        if ($date == 'default') {

            // create or update default settings
            $defaultExists = \App\Models\AutoUnit\DefaultSetting::where('siteId', $siteId)
                ->where('tipIdentifier', $tipIdentifier)
                ->count();

            if (! $defaultExists) {
                    $default = \App\Models\AutoUnit\DefaultSetting::create($r->all());
            } else {
                $default = \App\Models\AutoUnit\DefaultSetting::where('siteId', $siteId)
                    ->where('tipIdentifier', $tipIdentifier)
                    ->first();

                $default->minOdd = $r->input('minOdd');
                $default->maxOdd = $r->input('maxOdd');
                $default->prediction1x2 = $r->input('prediction1x2');
                $default->predictionOU = $r->input('predictionOU');
                $default->predictionAH = $r->input('predictionAH');
                $default->predictionGG = $r->input('predictionGG');
                $default->draw = $r->input('draw');
                $default->configType = $configType;
                $default->minWinrate = $minWinrate;
                $default->maxWinrate = $maxWinrate;
                $default->tipsPerDay = $tipsPerDay;
                $default->save();
            }

            // save associated leagues
            \App\Models\AutoUnit\League::where('siteId', $siteId)
                ->where('tipIdentifier', $tipIdentifier)
                ->where('type', 'default')
                ->delete();

            if (is_array($leagues) && count($leagues) > 0) {
                foreach ($leagues as $league) {
                    \App\Models\AutoUnit\League::create([
                        'siteId' => $siteId,
                        'tipIdentifier' => $tipIdentifier,
                        'leagueId' => $league,
                        'type' => 'default',
                    ]);
                }
            }

            return [
                'type' => 'success',
                'message' => '*** Default configuration was updated with success.'
            ];
        } else {

            if ($configType == 'tips') {
                if ($r->input('win') + $r->input('loss') + $r->input('draw') != $r->input('tipsNumber'))
                    return [
                        'type' => 'error',
                        'message' => 'Win + Loss + Draw must be equal with TipsNumber',
                    ];
            }

            if ($configType == 'days') {

                $dayInMonth = (int) date('t', strtotime($date . '-01'));
                $totalTips = $dayInMonth * $r->input('tipsPerDay');
                if ($r->input('win') + $r->input('loss') + $r->input('draw') != $totalTips)
                    return [
                        'type' => 'error',
                        'message' => 'Win + Loss + Draw must be equal with TipsPerDay * number of days in month (' . $totalTips . ')',
                    ];
            }

            // create or update monthly settings
            $defaultExists = \App\Models\AutoUnit\MonthlySetting::where('siteId', $siteId)
                ->where('tipIdentifier', $tipIdentifier)
                ->where('date', $date)
                ->count();

            if (! $defaultExists) {
                    $default = \App\Models\AutoUnit\MonthlySetting::create($r->all());
            } else {
                $default = \App\Models\AutoUnit\MonthlySetting::where('siteId', $siteId)
                    ->where('tipIdentifier', $tipIdentifier)
                    ->where('date', $date)
                    ->first();

                $default->date = $date;
                $default->minOdd = $r->input('minOdd');
                $default->maxOdd = $r->input('maxOdd');
                $default->prediction1x2 = $r->input('prediction1x2');
                $default->predictionOU = $r->input('predictionOU');
                $default->predictionAH = $r->input('predictionAH');
                $default->predictionGG = $r->input('predictionGG');
                $default->win = $r->input('win');
                $default->loss = $r->input('loss');
                $default->draw = $r->input('draw');
                $default->winrate = $r->input('winrate');
                $default->configType = $configType;
                $default->tipsPerDay = $tipsPerDay;
                $default->tipsNumber = $tipsNumber;
                $default->save();
            }

            // delete all schedule for selected month
            \App\Models\AutoUnit\DailySchedule::where('siteId', $siteId)
                ->where('tipIdentifier', $tipIdentifier)
                ->where('date', $date)
                ->delete();

            // create monthly schedule
            $scheduleInstance = new \App\Src\AutoUnit\Schedule($default);
            $scheduleInstance->createSchedule();

            foreach ($scheduleInstance->getSchedule() as $day) {
                \App\Models\AutoUnit\DailySchedule::create($day);
            }

            // save associated leagues
            \App\Models\AutoUnit\League::where('siteId', $siteId)
                ->where('tipIdentifier', $tipIdentifier)
                ->where('type', 'monthly')
                ->where('date', $date)
                ->delete();

            if (is_array($leagues) && count($leagues) > 0) {
                foreach ($leagues as $league) {
                    \App\Models\AutoUnit\League::create([
                        'siteId' => $siteId,
                        'leagueId' => $league,
                        'tipIdentifier' => $tipIdentifier,
                        'type' => 'monthly',
                        'date' => $date,
                    ]);
                }
            }

            return [
                'type' => 'success',
                'message' => '*** Monthly configuration was updated with success.'
            ];
        }


        return [
            'type' => 'error',
            'message' => 'Unknown configuration type',
        ];
    });

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

        // get events for archive
        $archiveEvents = \App\ArchiveBig::select(
                "archive_big.*",
                "match.sites_distributed_counter"
            )
            ->where('siteId', $siteId)
            ->join("event", "event.id", "archive_big.eventId")
            ->join("match", "match.id", "event.matchId")
            ->where('tableIdentifier', $tableIdentifier)
            ->where('systemDate', '>=', $date . '-01')
            ->where('systemDate', '<=', $date . '-31')
            ->get()
            ->toArray();

        foreach ($archiveEvents as $k => $v) {

            $archiveEvents[$k]['isRealUser'] = false;
            $archiveEvents[$k]['isNoUser']   = true;
            $archiveEvents[$k]['isAutoUnit'] = false;

            // check if event was for real users
            if (\App\SubscriptionTipHistory::where('eventId', $v['eventId'])->where('siteId', $v['siteId'])->count()) {
                $archiveEvents[$k]['isRealUser'] = true;
                $archiveEvents[$k]['isNoUser']   = false;
            }

            if ($archiveEvents[$k]['isNoUser']) {
                if ($v['provider'] == 'autounit') {
                    $archiveEvents[$k]['isNoUser']   = false;
                    $archiveEvents[$k]['isAutoUnit'] = true;
                }
            }

            // we must move the flag for table type fron association to archive
            $archiveEvents[$k]['isPosted']    = true;
            $archiveEvents[$k]['isScheduled'] = false;

            if ($v['statusId'] == 1)
                $win++;

            if ($v['statusId'] == 2)
                $loss++;

            if ($v['statusId'] == 3)
                $draw++;

            if ($v['statusId'] == 4)
                $postp++;
        }

        usort($archiveEvents, function($a, $b) {
            return strtotime($b['systemDate']) - strtotime($a['systemDate']);
        });

        $minDate = null;
        if (!empty($archiveEvents))
            $minDate = $archiveEvents[0]['systemDate'];

        // get scheduled events
        $scheduledEvents = \App\Models\AutoUnit\DailySchedule::where('siteId', $siteId)
            ->leftJoin("match", "match.primaryId", "auto_unit_daily_schedule.match_id")
            ->leftJoin("odd", "odd.id", "auto_unit_daily_schedule.odd_id")
            ->where('tableIdentifier', $tableIdentifier)
            ->where('date', $date)
            ->get()
            ->toArray();

        foreach ($scheduledEvents as $k => $v) {
            $scheduledEvents[$k]['homeTeam'] = $v["homeTeam"] ? $v["homeTeam"] : "?";
            $scheduledEvents[$k]['awayTeam'] = $v["awayTeam"] ? $v["awayTeam"] : "?";
            $scheduledEvents[$k]['league']   = $v["league"] ? $v["league"] : "?";
            $scheduledEvents[$k]['odd']      = $v["odd"] ? $v["odd"] : "?";
            $scheduledEvents[$k]['result']      = $v["result"] ? $v["result"] : "?";

            $scheduledEvents[$k]['isRealUser'] = false;
            $scheduledEvents[$k]['isNoUser']   = false;
            $scheduledEvents[$k]['isAutoUnit'] = true;

            $scheduledEvents[$k]['isPosted']    = false;
            $scheduledEvents[$k]['isScheduled'] = true;

            // unset oldest scheduled events
            if ($minDate != null) {
                if (strtotime($minDate) >= strtotime($v['systemDate'])) {
                    unset($scheduledEvents[$k]);
                    continue;
                }
            }

            if ($v['statusId'] == 1)
                $win++;

            if ($v['statusId'] == 2)
                $loss++;

            if ($v['statusId'] == 3)
                $draw++;

            if ($v['statusId'] == 4)
                $postp++;
        }

        $allEvents = array_merge($scheduledEvents, $archiveEvents);

        usort($allEvents, function($a, $b) {
            return strtotime($b['systemDate']) - strtotime($a['systemDate']);
        });

        return [
            'events' => $allEvents,
            'win'    => $win,
            'loss'   => $loss,
            'draw'   => $draw,
            'postp'  => $postp,
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