<?php namespace App\Console\Commands;

use App\Models\AutoUnit\AdminPool;
use Illuminate\Support\Facades\DB;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class AutoUnitAddEvents extends CronCommand
{
    protected $name = 'autounit:add-events';
    protected $signature = 'autounit:add-events {--site=} {--table=}';
    protected $description = 'Add events according to autounit schedule.';

    private $log = null;
    private $systemDate;
    private $todayAdminPoolEvents = [];

    private $allLeagues = [];
    private $useAllLeagues = false;
    private $minimCondition = 0;
    private $maximumCondition = 0;
    private $isFromAdminPool = 0;

    private $predictions = [];

    private $SiteAssocEvents = [];

    public function fire($matchWithResult = null, $changeMatch = false, $scheduleId = null, $postponed = false, $predictionChange = false)
    {
        $currentDate = date("Y-m-d", time());
        $this->log = new Logger($currentDate . '_autounit_logs');
        $this->log->pushHandler(new StreamHandler(storage_path('logs/' . $currentDate . '_autounit_logs.log')), Logger::INFO);

        if ($matchWithResult !== null || $postponed) {
            $matchPredictionResults = json_decode($matchWithResult->prediction_results);
            $this->systemDate = gmdate('Y-m-d', strtotime($matchWithResult->eventDate));
            $schedules = $this->getAutoUnitFilteredSchedule($this->systemDate, $matchWithResult->primaryId, $scheduleId, $predictionChange);
        } else {
            $this->systemDate = gmdate('Y-m-d');
            $schedules = $this->getAutoUnitTodaySchedule($scheduleId);
        }

        $info = [
            'created' => 0,
            'message' => []
        ];

        // set all leagues
        foreach (\App\League::all() as $l) {
            $this->allLeagues[] = $l->id;
        }

        // load today finished events
        $this->setTodayEvents();

        if (! count($this->todayAdminPoolEvents)) {

            $info['message'] = 'There is no finished events yet';

            $this->log->log(500, json_encode([
                "message"           => "There are no valid matches in admin pool"
            ]));
            $this->log->log(500, "####################################################");
            return true;
        }

        foreach ($schedules as $schedule) {
            $package = \App\Package::where('siteId', $schedule['siteId'])
                        ->where('tipIdentifier', $schedule['tipIdentifier'])
                        ->where('tableIdentifier', $schedule['tableIdentifier'])
                        ->first();

            if ($package && $package->paused_autounit) {
                $this->log->log(400, json_encode([
                    "packageId"         => $package->id,
                    "siteId"            => $schedule['siteId'],
                    "tableIdentifier"   => $schedule['tableIdentifier'],
                    "tipIdentifier"     => $schedule['tipIdentifier'],
                    "predictionGroup"   => $schedule['predictionGroup'],
                    "message"           => "Package is paused"
                ]));
                $this->log->log(400, "####################################################");

                continue;
            }
            $eventExists = \App\Distribution::where('siteId', $schedule['siteId'])
                ->where('tipIdentifier', $schedule['tipIdentifier'])
                ->where('provider', '!=', 'autounit')
                ->where('systemDate', $this->systemDate)
                ->count();

            if ($eventExists) {
                \App\Models\Autounit\DailySchedule::find($schedule['id'])
                    ->update([
                        'status' => 'eventExists',
                        'info'   => json_encode(['Event already exists for: ' . $this->systemDate]),
                    ]);
                continue;
            }

            $leagues = $this->getAssociatedLeaguesBySchedule(
                $schedule['siteId'],
                $schedule['date'],
                $schedule['tipIdentifier']
            );

            // add log if there is not any leagues associated with schedule
            if (! count($leagues)) {

                // add log if not exists or it is solved
                $checksum = md5($schedule['id'] . $schedule['siteId'] . 'autounit' . $schedule['tipIdentifier']);
                if (! \App\Models\Log::where('identifier', $checksum)->where('status', 1)->count()) {
                    $site = \App\Site::find($schedule['siteId']);
                    \App\Models\Log::create([
                        'type' => 'warning',
                        'module' => 'autounit',
                        'identifier' => $checksum,
                        'status' => 1,
                        'info' => json_encode(["Site: " . $site->name . " has no leagues associated for tip: " . $schedule['tipIdentifier'] . ", will try to find an event in all leagues"]),
                    ]);
                }
            }

            // get minOdd and maxOdd
            $monthSetting = \App\Models\AutoUnit\MonthlySetting::where('date', $schedule['date'])
                ->where('siteId', $schedule['siteId'])
                ->where('tipIdentifier', $schedule['tipIdentifier'])
                ->first();

            $schedule['minOdd'] = $monthSetting->minOdd;
            $schedule['maxOdd'] = $monthSetting->maxOdd;

            $leagueArr = [];
            foreach ($leagues as $league) {
                $leagueArr[] = $league['leagueId'];
            }

            // set prediction according to schedule
            $this->setPredictions($schedule);

            // verify if the match that got result from the feed
            // has the same statusId as the match in the auto-unit schedule
            if ($matchWithResult !== null && $changeMatch == false) {
                if ($matchPredictionResults) {
                    foreach ($this->predictions as $prediction) {
                        $found = array_search($prediction, array_column($matchPredictionResults, 'predictionName'));
                        if ($found !== false) {
                            $matchWithResult->statusId = $matchPredictionResults[$found]->value;
                            break;
                        }
                    }
                }
                if ($schedule["statusId"] != $matchWithResult->statusId && $matchWithResult->statusId != 4) {
                    $odd = \App\Models\Events\Odd::where('id', $schedule['odd_id'])->first();

                    $invalidMatches = json_decode($schedule["invalid_matches"]);
                    $invalidMatches[] = $matchWithResult->homeTeam . " - " . $matchWithResult->awayTeam . " - " . $matchWithResult->result  . " - " .$odd->predictionId;
                    
                    \App\Models\Autounit\DailySchedule::find($schedule['id'])
                    ->update([
                        'invalid_matches' => json_encode($invalidMatches)
                    ]);

                    $this->log->log(400, json_encode([
                        "wantedStatusResult"        => $schedule["statusId"],
                        "matchStatusResult"         => $matchWithResult->statusId,
                        "match"                     => $matchWithResult->homeTeam . " - " . $matchWithResult->awayTeam,
                        "siteId"                    => $schedule['siteId'],
                        "tableIdentifier"           => $schedule['tableIdentifier'],
                        "tipIdentifier"             => $schedule['tipIdentifier'],
                        "predictionGroup"           => $schedule['predictionGroup'],
                        "message"                   => "Match ended with invalid prediction result, will try to change match"
                    ]));
                    $this->log->log(400, "####################################################");

                    $this->incrementDistributedCounter($matchWithResult["id"], -1);
                    $this->fire($matchWithResult, true, $schedule["id"]);
                    continue;
                } elseif ($changeMatch == false) {
                    $event = [];
                    $odd = \App\Models\Events\Odd::where('id', $schedule['odd_id'])->first();

                    $event["homeTeamId"] = $matchWithResult->homeTeamId;
                    $event["awayTeamId"] = $matchWithResult->awayTeamId;
                    $event["eventDate"] = $matchWithResult->eventDate;
                    $event["predictionId"] = $odd->predictionId;
                    $event["countryCode"] = $matchWithResult->countryCode;
                    $event["country"] = $matchWithResult->country;
                    $event["to_distribute"] = true;

                    $packages = \App\Package::where('siteId', $schedule['siteId'])
                        ->where('tipIdentifier', $schedule['tipIdentifier'])
                        ->get();
                    $eventModel = $this->getOrCreateEvent($event);
                    \App\Association::where('eventId', $eventModel['id'])
                                ->where('predictionId', $event['predictionId'])
                                ->update([
                                    'to_distribute' => true
                                ]);

                    $associations = \App\Association::select("id")
                                        ->where('eventId', $eventModel['id'])
                                        ->where('predictionId', $event['predictionId'])
                                        ->get()
                                        ->toArray();

                    $distribution = \App\Distribution::whereIn('associationId', array_column($associations, "id"))
                                        ->update([
                                            'to_distribute' => true,
                                            'result' => $matchWithResult->result
                                        ]);

                    \App\Models\Autounit\DailySchedule::find($schedule['id'])
                        ->update([
                            'to_distribute' => true,
                            'status' => 'success',
                            'info'   => json_encode(['Eligible event.']),
                        ]);
                    continue;
                }
            } else if ($matchWithResult !== null && $postponed) {
                $odd = \App\Models\Events\Odd::where('id', $schedule['odd_id'])->first();

                $invalidMatches = json_decode($schedule["invalid_matches"]);
                $invalidMatches[] = $matchWithResult->homeTeam . " - " . $matchWithResult->awayTeam . " - " . $odd->predictionId . " - POSTPONED";
                
                \App\Models\Autounit\DailySchedule::find($schedule['id'])
                ->update([
                    'invalid_matches'   => json_encode($invalidMatches)
                ]);
                
                $this->log->log(400, json_encode([
                    "match"             => $matchWithResult->homeTeam . " - " . $matchWithResult->awayTeam,
                    "siteId"            => $schedule['siteId'],
                    "tableIdentifier"   => $schedule['tableIdentifier'],
                    "tipIdentifier"     => $schedule['tipIdentifier'],
                    "predictionGroup"   => $schedule['predictionGroup'],
                    "message"           => "Match was postponed, will try to change the match"
                ]));
                $this->log->log(400, "####################################################");

                $this->incrementDistributedCounter($matchWithResult["id"], -1);
                $this->fire($matchWithResult, true, $schedule["id"]);
                continue;
            }

            if ($changeMatch) {
                // delete the events for the invalid match
                // if none of the autounit schedules have it
                $odd = \App\Models\Events\Odd::where('id', $schedule['odd_id'])->first();
                $this->deleteInvalidDistribution($matchWithResult, $schedule, $odd);
                $this->checkScheduledMatchExists($matchWithResult, $schedule, $odd);
            }
            
            // the auto-unit first checks in the admin-pool list of events
            // if it didn't find any event within that pool, that satisfies the site configuration for the auto-unit
            // it will continue to search within the rest of the events inside the application
            $this->minimCondition = 0;
            do {
                $event = $this->chooseEvent($schedule, $leagueArr, $this->todayAdminPoolEvents);
                $this->minimCondition += 1;
            } while (($this->minimCondition <= $this->maximumCondition) && $event == null);

            if ($event == null) {
                $this->isFromAdminPool = 0;
                $checksum = md5($schedule['id'] . $schedule['siteId'] . 'autounit-admin-pool' . $schedule['tipIdentifier']);
                if (! \App\Models\Log::where('identifier', $checksum)->where('status', 1)->count()) {
                    $site = \App\Site::find($schedule['siteId']);
                    \App\Models\Log::create([
                        'type' => 'warning',
                        'module' => 'autounit',
                        'identifier' => $checksum,
                        'status' => 1,
                        'info' => json_encode(["Site: " . $site->name . " could not find any event in the admin pool for tip: " . $schedule['tipIdentifier']]),
                    ]);
                }

                \App\Models\Autounit\DailySchedule::find($schedule['id'])
                    ->update([
                        'status' => 'error',
                        'info'   => json_encode(['Could not find any event in the admin pool']),
                    ]);

                    $this->log->log(400, json_encode([
                        "siteId"                    => $schedule['siteId'],
                        "tableIdentifier"           => $schedule['tableIdentifier'],
                        "tipIdentifier"             => $schedule['tipIdentifier'],
                        "predictionGroup"           => $schedule['predictionGroup'],
                        "message"                   => "Could not find any event for this schedule"
                    ]));
                    $this->log->log(400, "####################################################");
                continue;
            } else {
                $this->isFromAdminPool = 1;
            }

            $info['created']++;

            $eventModel = $this->getOrCreateEvent($event);

            $this->log->log(100, json_encode([
                "match"                     => $event["homeTeam"] . " - " . $event["awayTeam"],
                "siteId"                    => $schedule['siteId'],
                "tableIdentifier"           => $schedule['tableIdentifier'],
                "tipIdentifier"             => $schedule['tipIdentifier'],
                "predictionGroup"           => $schedule['predictionGroup'],
                "message"                   => "Event is set"
            ]));
            $this->log->log(100, "####################################################");

            // get all packages according to schedule
            $packages = \App\Package::where('siteId', $schedule['siteId'])
                ->where('tipIdentifier', $schedule['tipIdentifier'])
                ->get();

            if (isset($event["error"]) && $event["error"]) {
                \App\Models\Autounit\DailySchedule::find($schedule['id'])
                ->update([
                    'is_from_admin_pool' => $this->isFromAdminPool,
                    'status' => 'error',
                    'info'   => json_encode(['Match result status is invalid']),
                ]);

                $this->log->log(400, json_encode([
                    "siteId"                    => $schedule['siteId'],
                    "tableIdentifier"           => $schedule['tableIdentifier'],
                    "tipIdentifier"             => $schedule['tipIdentifier'],
                    "predictionGroup"           => $schedule['predictionGroup'],
                    "message"                   => "Match result is invalid, skipping scheduling"
                ]));
                $this->log->log(400, "####################################################");
                continue;
            }
            $this->distributeEvent($eventModel, $packages);
            if ($event["to_distribute"]) {
                \App\Models\Autounit\DailySchedule::find($schedule['id'])
                ->update([
                    'to_distribute' => true,
                    'is_from_admin_pool' => $this->isFromAdminPool,
                    'status' => 'success',
                    'info'   => json_encode(['Eligible event.']),
                ]);
            } else {
                \App\Models\Autounit\DailySchedule::find($schedule['id'])
                ->update([
                    'to_distribute' => false,
                    'is_from_admin_pool' => $this->isFromAdminPool,
                    'status' => 'waiting',
                    'info'   => json_encode(['Ineligible event.']),
                ]);
            }
            $this->incrementDistributedCounter($event["matchId"], 1);
        }
        
        $this->deleteEmptyAutounitAssociations();
        //$this->info(json_encode($info));
        //$this->stopCron($cron, $info);

        return true;
    }

    // this will get if exist or create event from match
    // @return array()
    private function getOrCreateEvent(array $event)
    {
        // get event or create it
        $ev = \App\Event::where('homeTeamId', $event['homeTeamId'])
            ->where('awayTeamId', $event['awayTeamId'])
            ->where('eventDate', $event['eventDate'])
            ->where('predictionId', $event['predictionId'])
            ->first();

        if (! $ev) {
			// get the aliases - added by GDM
			$homeTeamAlias = \App\Models\Team\Alias::where('teamId', $event['homeTeamId'] )->first();
			if( $homeTeamAlias && $homeTeamAlias->alias && $homeTeamAlias->alias != '' ) {
				$event['homeTeam'] = $homeTeamAlias->alias;
			}		
			$awayTeamAlias = \App\Models\Team\Alias::where('teamId', $event['awayTeamId'] )->first();
			if( $awayTeamAlias && $awayTeamAlias->alias && $awayTeamAlias->alias != '' ) {
				$event['awayTeam'] = $awayTeamAlias->alias;
			}		
			$leagueAlias = \App\Models\League\Alias::where('leagueId', $event['leagueId'] )->first();
			if( $leagueAlias && $leagueAlias->alias && $leagueAlias->alias != '' ) {
				$event['league'] = $leagueAlias->alias;
			}
			
			$countryAlias = \App\Models\Country\Alias::where('countryCode', $event['countryCode'] )->first();
			if( $countryAlias && $countryAlias->alias && $countryAlias->alias != '' ) {
				$event['country'] = $countryAlias->alias;
			}
            $ev = \App\Event::create($event);
            $ev = $ev->toArray();
		}
        $ev["to_distribute"] = $event["to_distribute"];
        
        return is_array($ev) ? $ev : $ev->toArray();
    }

    private function getOrCreateAssociation($event)
    {
        $assoc = \App\Association::where('eventId', $event['id'])
            ->where('type', $event['type'])
            ->where('predictionId', $event['predictionId'])
            ->where("provider", "=", "autounit")
            ->first();

        if (! $assoc) {
            $event['eventId'] = (int)$event['id'];
            $event["provider"] = "autounit";
            unset($event['id']);
            unset($event['created_at']);
            unset($event['updated_at']);

            $event['isNoTip'] = '';
            $event['systemDate'] = $this->systemDate;
			
			// get the aliases - added by GDM
			$homeTeamAlias = \App\Models\Team\Alias::where('teamId', $event['homeTeamId'] )->first();
			if( $homeTeamAlias && $homeTeamAlias->alias && $homeTeamAlias->alias != '' ) {
				$event['homeTeam'] = $homeTeamAlias->alias;
			}		
			$awayTeamAlias = \App\Models\Team\Alias::where('teamId', $event['awayTeamId'] )->first();
			if( $awayTeamAlias && $awayTeamAlias->alias && $awayTeamAlias->alias != '' ) {
				$event['awayTeam'] = $awayTeamAlias->alias;
			}		
			$leagueAlias = \App\Models\League\Alias::where('leagueId', $event['leagueId'] )->first();
			if( $leagueAlias && $leagueAlias->alias && $leagueAlias->alias != '' ) {
				$event['league'] = $leagueAlias->alias;
			}
			
			$countryAlias = \App\Models\Country\Alias::where('countryCode', $event['countryCode'] )->first();
			if( $countryAlias && $countryAlias->alias && $countryAlias->alias != '' ) {
				$event['country'] = $countryAlias->alias;
			}
			

            $assoc = \App\Association::create($event);
        }

        return $assoc->toArray();
    }

    // this will propagate event in all app
    private function distributeEvent($event, $packages)
    {
        foreach ($packages as $package) {

            $event['type'] = 'nun';

            if ($package->isVip) {
                $event['type'] = 'nuv';
                $event['isVip'] = 1;
            }

            // create association
            $assoc = $this->getOrCreateAssociation($event);
            $assoc['associationId'] = $assoc['id'];
            unset($assoc['id']);
            unset($assoc['created_at']);
            unset($assoc['updated_at']);

            $sitePrediction = \App\SitePrediction::where('siteId', $package->siteId)
                ->where('predictionIdentifier', $assoc['predictionId'])
                ->first();

            $assoc['predictionName'] = $sitePrediction->name;
            $assoc['siteId'] = $package->siteId;
            $assoc['tableIdentifier'] = $package->tableIdentifier;
            $assoc['tipIdentifier'] = $package->tipIdentifier;
            $assoc['packageId'] = $package->id;
			
			// get the aliases - added by GDM
			$homeTeamAlias = \App\Models\Team\Alias::where('teamId', $assoc['homeTeamId'] )->first();
			if( $homeTeamAlias && $homeTeamAlias->alias && $homeTeamAlias->alias != '' ) {
				$assoc['homeTeam'] = $homeTeamAlias->alias;
			}		
			$awayTeamAlias = \App\Models\Team\Alias::where('teamId', $assoc['awayTeamId'] )->first();
			if( $awayTeamAlias && $awayTeamAlias->alias && $awayTeamAlias->alias != '' ) {
				$assoc['awayTeam'] = $awayTeamAlias->alias;
			}		
			$leagueAlias = \App\Models\League\Alias::where('leagueId', $assoc['leagueId'] )->first();
			if( $leagueAlias && $leagueAlias->alias && $leagueAlias->alias != '' ) {
				$assoc['league'] = $leagueAlias->alias;
			}
			
			$countryAlias = \App\Models\Country\Alias::where('countryCode', $assoc['countryCode'] )->first();
			if( $countryAlias && $countryAlias->alias && $countryAlias->alias != '' ) {
				$assoc['country'] = $countryAlias->alias;
			}
			

            \App\Distribution::create($assoc);
        }
    }

    // this will choose event from all today schedule events
    // @param array $schedule
    // @param array $leagues leagueId => true
    // @return array()
    private function chooseEvent(array $schedule, array $leagues, array &$finishedEvents)
    {
        if (! count($leagues))
            return null;

        $index = rand(0, count($leagues) -1);
        $leagueId = $leagues[$index];

        //  if league not have events today unset current index and reset keys
        if (! array_key_exists((int)$leagueId, $finishedEvents)) {
            /*
            $this->log->log(400, json_encode([
                "leagueId"          => $leagueId,
                "siteId"            => $schedule['siteId'],
                "tableIdentifier"   => $schedule['tableIdentifier'],
                "tipIdentifier"     => $schedule['tipIdentifier'],
                "message"           => "Could not find event in the randomly selected league, will try another league"
            ]));
            $this->log->log(400, "----------------------------------------------------");
            */
            return $this->chooseEvent($schedule, $this->unsetIndex($leagues, $index), $finishedEvents);
        }

        $event = $this->getWinnerEvent($schedule, $finishedEvents[$leagueId], $leagueId, $finishedEvents);

        //  if not found event unset current index and reset keys
        if ($event == null) {
            $this->log->log(400, json_encode([
                "leagueId"          => $leagueId,
                "siteId"            => $schedule['siteId'],
                "tableIdentifier"   => $schedule['tableIdentifier'],
                "tipIdentifier"     => $schedule['tipIdentifier'],
                "message"           => "Could not find any event in the current league that respects the conditions, will try in another league"
            ]));
            $this->log->log(400, "----------------------------------------------------");
            return $this->chooseEvent($schedule, $this->unsetIndex($leagues, $index), $finishedEvents);
        }

        $scheduleModel = \App\Models\AutoUnit\DailySchedule::where("id", "=", $schedule["id"])
            ->update([
                "match_id" => $event["primaryId"],
                "to_distribute" => $event["to_distribute"],
                "odd_id" => $event["oddId"]
            ]);
        return $event;
    }

    private function getWinnerEvent($schedule, $events, $leagueId, &$totalEvents)
    {
        if (! count($events))
            return null;

        $index = rand(0, count($events) -1);
        $event = $events[$index];

        // get odds for event
        $odds = \App\Models\Events\Odd::where('matchId', $event['id'])
            ->where('leagueId', $event['leagueId'])
            ->whereIn('predictionId', $this->predictions)
            ->whereBetween ('odd', [$schedule['minOdd'], $schedule['maxOdd']])
            ->get()
            ->toArray();

        // Try next event if there is no odds
        if (! count($odds)) {
            $this->log->log(400, json_encode([
                "match"             => $event['homeTeam'] . " - " . $event["awayTeam"],
                "oddInterval"       => $schedule['minOdd'] . " <-> " .  $schedule['maxOdd'],
                "siteId"            => $schedule['siteId'],
                "tableIdentifier"   => $schedule['tableIdentifier'],
                "tipIdentifier"     => $schedule['tipIdentifier'],
                "predictionGroup"   => $schedule['predictionGroup'],
                "message"           => "Could not find odds for the randomly selected match, will try another"
            ]));
            $this->log->log(400, "----------------------------------------------------");
            return $this->getWinnerEvent($schedule, $this->unsetIndex($events, $index), $leagueId, $totalEvents);
        }
        // try to find correct status base on odd
        foreach ($odds as $odd) {
            $statusByScore = new \App\Src\Prediction\SetStatusByScore($event['result'], $odd['predictionId']);
            $statusByScore->evaluateStatus();
            $statusId = $statusByScore->getStatus();
        
            if ($statusId == -1 && $event["result"] != "") {
                $event['error'] = true;
            }

            if ($statusId < 1 || ($statusId == $schedule['statusId']) ) {
                $event['matchId'] = $event['id'];
                $event['source'] = 'feed';
                $event['provider'] = 'autounit';
                unset($event['id']);
                unset($event['created_at']);
                unset($event['updated_at']);
                $event['odd'] = $odd['odd'];
                $event['oddId'] = $odd['id'];
                $event['predictionId'] = $odd['predictionId'];
                $event['statusId'] = $statusId;
                $event['systemDate'] = $this->systemDate;
            } else if ($statusId != $schedule['statusId']) {
                continue;
            }
            
            if ($statusId == $schedule['statusId']) {
                $event['to_distribute'] = true;
            } else {
                $event['to_distribute'] = false;
            }
            // set the random event index from the list
            $event["index"] = $index;
            
            // check if the match was already distributed in the site
            // a site cannot have the same match distributed twice with different predictions
            if ($this->isMatchDistributed($event, $schedule)) {
                return $this->getWinnerEvent($schedule, $this->unsetIndex($events, $index), $leagueId, $totalEvents);
            }
            
            // check if the event was already distributed in a different site
            // events that were not distributed at all
            // or have a lower value than the MAX number of events distributed in a site
            // have a higher priority
            if ($this->isDistributedTooManyTimes($event, $totalEvents)) {
                return $this->getWinnerEvent($schedule, $this->unsetIndex($events, $index), $leagueId, $totalEvents);
            }
            $totalEvents[$leagueId][$index]["sites_distributed_counter"] += 1;
            return $event;
        }

        return $this->getWinnerEvent($schedule, $this->unsetIndex($events, $index), $leagueId, $totalEvents);
    }

    // @param array $schedule
    // set associated predictions
    // @retun void
    private function setPredictions(array $schedule) : void
    {
        $package = \App\Package::where('siteId', $schedule['siteId'])
            ->where('tipIdentifier', $schedule['tipIdentifier'])
            ->first();

        $assocPredictions = \App\PackagePrediction::where('packageId', $package->id)
            ->get();

        $assocPred = [];
        foreach ($assocPredictions as $ap)
            $assocPred[] = $ap->predictionIdentifier;

        $predictions = \App\Prediction::where('group', $schedule['predictionGroup'])
            ->whereIn('identifier', $assocPred)
            ->get()
            ->toArray();

        $pred = [];
        foreach ($predictions as $prediction) {
            $pred[] = $prediction['identifier'];
        }
        $this->predictions = $pred;

        $this->log->log(400, json_encode([
            "validPredictions"  => $this->predictions,
            "siteId"            => $schedule['siteId'],
            "tableIdentifier"   => $schedule['tableIdentifier'],
            "tipIdentifier"     => $schedule['tipIdentifier'],
            "predictionGroup"   => $schedule['predictionGroup'],
            "message"           => "Valid predictions"
        ]));
        $this->log->log(400, "----------------------------------------------------");
    }

    // @ param int $siteId
    // @ param string $date
    // @ param string $tipIdentifier
    // get associated leagues with tip Identifier
    // @return array()
    private function getAssociatedLeaguesBySchedule($siteId, $date, $tipIdentifier) : array
    {
        return \App\Models\AutoUnit\League::select('leagueId')
            ->where('tipIdentifier', $tipIdentifier)
            ->where('siteId', $siteId)
            ->get()
            ->toArray();
    }

    // unset key from array and reindex keys
    // @param array $arr
    // @param integer $ind
    // @return array()
    private function unsetIndex(array $arr,  int $ind) : array
    {
        unset($arr[$ind]);
        return count($arr) > 0 ? array_values($arr) : $arr;
    }

    // set in $this->todayEvents all events finished today
    // @return void
    private function setTodayEvents()
    {
        $adminPoolEvents = AdminPool::getAutoUnitPoolMatches($this->systemDate);

        foreach ($adminPoolEvents as $adminPoolEvent) {
            $this->todayAdminPoolEvents[$adminPoolEvent["leagueId"]][] = $adminPoolEvent;
        }
    }

    // get full schedule for today from autounit
    // @return array()
    private function getAutoUnitTodaySchedule($scheduleId) : array
    {
        return \App\Models\AutoUnit\DailySchedule::where('systemDate', $this->systemDate)
            ->select(
                "auto_unit_daily_schedule.*"
            )
            ->join("site", "site.id", "=", "auto_unit_daily_schedule.siteId")
            ->where('status', '!=', 'success')
            ->whereNull('match_id')
            ->when($scheduleId, function($query, $scheduleId) {
                $query->where("id", $scheduleId);
            })
            ->when($this && $this->option("site"), function($query) {
                $query->where("auto_unit_daily_schedule.siteId", "=", $this->option("site"));
            })
            ->when($this && $this->option("table"), function($query) {
                $query->where("auto_unit_daily_schedule.tableIdentifier", "=", $this->option("table"));
            })
            ->get()
            ->toArray();
    }
    
    private function getAutoUnitFilteredSchedule($date, $matchId, $scheduleId, $predictionChange) : array
    {
        return \App\Models\AutoUnit\DailySchedule::where('systemDate', $date)
            ->select(
                "auto_unit_daily_schedule.*"
            )
            ->join("site", "site.id", "=", "auto_unit_daily_schedule.siteId")
            ->when(!$predictionChange, function($query) {
                $query->where('status', '=', 'waiting');
            })
            ->where('match_id', '=', $matchId)
            ->when($scheduleId, function($query, $scheduleId) {
                $query->where("auto_unit_daily_schedule.id", $scheduleId);
            })
            ->get()
            ->toArray();
    }
    
    private function isMatchDistributed(array $event, array $schedule) : bool
    {
        $distributed = \App\Models\AutoUnit\DailySchedule::where("match_id", "=", $event["primaryId"])
            ->where("systemDate", "=", $event['systemDate'])
            ->where("siteId", "=", $schedule["siteId"])
            ->exists();

        if ($distributed) {
            $this->log->log(400, json_encode([
                "match"             => $event["homeTeam"] . " - " . $event["awayTeam"],
                "siteId"            => $schedule["siteId"],
                "tableIdentifier"   => $schedule["tableIdentifier"],
                "tipIdentifier"     => $schedule["tipIdentifier"],
                "predictionGroup"   => $schedule['predictionGroup'],
                "message"           => "Match was already distributed, will try to find another one"
            ]));
            $this->log->log(400, "----------------------------------------------------");

            return true;
        }
        return false;
    }
    
    private function isDistributedTooManyTimes(array $event, array &$finishedEvents) : bool
    {
        $matchIds = [];
        $matchModel = \App\Match::where("id" , "=", $event["matchId"])->first();

        // map the match ids so we can limit the search
        foreach ($finishedEvents as $key => $value) {
            foreach ($value as $finishedEvent) {
                $matchIds[] = $finishedEvent["primaryId"];
            }
        }

        $match = \App\Match::select(
                DB::raw("MAX(sites_distributed_counter) AS maxCounter"),
                DB::raw("MIN(sites_distributed_counter) AS minCounter")
            )
            ->whereRaw("DATE_FORMAT(eventDate, '%Y-%m-%d') = '" . $this->systemDate . "'")
            ->whereIn("primaryId", $matchIds)
            ->first();

        $this->maximumCondition = $match->maxCounter;
        
        if (
            $matchModel->sites_distributed_counter == ($match->minCounter + $this->minimCondition) &&
            $match->minCounter + $this->minimCondition <= $this->maximumCondition
        ) {
            return false;
        }

        $this->log->log(400, json_encode([
            "match"             => $event["homeTeam"] . " - " . $event["awayTeam"],
            "message"           => "Match is used by too many sites, will try to find another one"
        ]));
        $this->log->log(400, "----------------------------------------------------");

        return true;
    }
    
    private function incrementDistributedCounter(int $matchId , int $value) : void
    {
        $match = \App\Match::where("id", "=", $matchId)->increment("sites_distributed_counter", (int)$value);
    }

    private function checkScheduledMatchExists($match, $schedule, $odd)
    {
        $scheduledMatch = \App\Models\Autounit\DailySchedule::where('match_id', '=', $match->primaryId)
                        ->where("odd_id", "=", $schedule["odd_id"])
                        ->where("id", "!=", $schedule["id"])
                        ->exists();

        $distributedMatch = \App\Distribution::where("systemDate", "=", $schedule["systemDate"])
            ->where('leagueId', $match["leagueId"])
            ->where("homeTeamId", "=", $match["homeTeamId"])
            ->where("awayTeamId", "=", $match["awayTeamId"])
            ->exists();

        $odd = \App\Models\Events\Odd::where('id', $schedule['odd_id'])->first();

        if (!$scheduledMatch && !$distributedMatch) {
            \App\Event::where('matchId', $match->id)
                ->where('provider', '=', 'autounit')
                ->where('predictionId', '=', $odd->predictionId)
                ->delete();
            $this->deleteInvalidAssociation($match, $schedule, $odd);

            $this->log->log(400, json_encode([
                "match"             => $match->homeTeam . " - " . $match->awayTeam,
                "siteId"            => $schedule['siteId'],
                "tableIdentifier"   => $schedule['tableIdentifier'],
                "tipIdentifier"     => $schedule['tipIdentifier'],
                "predictionGroup"   => $schedule['predictionGroup'],
                "prediction"        => $odd["predictionId"],
                "message"           => "Deleted event and associations since no scheduled match and no distributions exist"
            ]));
            $this->log->log(400, "----------------------------------------------------");
        }
    }

    private function deleteInvalidDistribution($match, $schedule, $odd)
    {
        \App\Distribution::where("siteId", "=", $schedule["siteId"])
            ->where("tableIdentifier", "=", $schedule["tableIdentifier"])
            ->where("tipIdentifier", "=", $schedule["tipIdentifier"])
            ->where("systemDate", "=", $schedule["systemDate"])
            ->where('leagueId', $match["leagueId"])
            ->where("homeTeamId", "=", $match["homeTeamId"])
            ->where("awayTeamId", "=", $match["awayTeamId"])
            ->where("provider", "=", "autounit")
            ->where("predictionId", "=", $odd["predictionId"])
            ->where("to_distribute", "=", 0)
            ->delete();

            $this->log->log(400, json_encode([
                "match"             => $match->homeTeam . " - " . $match->awayTeam,
                "siteId"            => $schedule['siteId'],
                "tableIdentifier"   => $schedule['tableIdentifier'],
                "tipIdentifier"     => $schedule['tipIdentifier'],
                "predictionGroup"   => $schedule['predictionGroup'],
                "prediction"        => $odd["predictionId"],
                "message"           => "Deleted invalid distributions"
            ]));
            $this->log->log(400, "----------------------------------------------------");
    }
    
    private function deleteInvalidAssociation($match, $schedule, $odd)
    {
        \App\Association::where("systemDate", "=", $schedule["systemDate"])
            ->where('leagueId', $match["leagueId"])
            ->where("homeTeamId", "=", $match["homeTeamId"])
            ->where("awayTeamId", "=", $match["awayTeamId"])
            ->where("provider", "=", "autounit")
            ->where("predictionId", "=", $odd["predictionId"])
            ->delete();
    }
    
    private function deleteEmptyAutounitAssociations()
    {
        $associations = \App\Association::select("association.id", "association.eventId")
            ->where("association.systemDate", "=", $this->systemDate)
            ->where("association.provider", "=", "autounit")
            ->leftJoin("distribution", "distribution.associationId", "association.id")
            ->whereNull("distribution.id")
            ->get()
            ->toArray();

        if (!empty($associations)) {
            \App\Event::where("id", "=", $associations[0]["eventId"])->delete();
        }
        \App\Association::whereIn('id', array_column($associations, "id"))
            ->delete();
    }
}

