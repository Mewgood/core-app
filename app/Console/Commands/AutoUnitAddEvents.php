<?php namespace App\Console\Commands;

use App\Models\AutoUnit\AdminPool;
use Illuminate\Support\Facades\DB;

class AutoUnitAddEvents extends CronCommand
{
    protected $name = 'autounit:add-events';
    protected $description = 'Add events according to autounit schedule.';

    private $systemDate;
    private $todayEvents = [];
    private $todayAdminPoolEvents = [];

    private $allLeagues = [];
    private $useAllLeagues = false;

    private $predictions = [];

    private $SiteAssocEvents = [];

    public function fire($matchWithResult = null)
    {
        //$cron = $this->startCron();

        if ($matchWithResult !== null) {
            $matchPredictionResults = json_decode($matchWithResult->prediction_results);
            $this->systemDate = gmdate('Y-m-d', strtotime($matchWithResult->eventDate));
            $schedules = $this->getAutoUnitFilteredSchedule($this->systemDate, $matchWithResult->primaryId);
        } else {
            $this->systemDate = gmdate('Y-m-d');
            $schedules = $this->getAutoUnitTodaySchedule();
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

        if (! count($this->todayEvents)) {

            $info['message'] = 'There is no finished events yet';

            $this->info(json_encode($info));
            //$this->stopCron($cron, $info);
            return true;
        }

        foreach ($schedules as $schedule) {

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
            if ($matchWithResult !== null) {
                foreach ($this->predictions as $prediction) {
                    $found = array_search($prediction, array_column($matchPredictionResults, 'predictionName'));
                    if ($found !== false) {
                        $matchWithResult->statusId = $matchPredictionResults[$found]->value;
                        break;
                    }
                }
                if ($schedule["statusId"] != $matchWithResult->statusId) {                    
                    $message = "Invalid match result for schedule<" . $schedule["id"] . "> | Initial match<" . $schedule["match_id"] . ">";
                    echo $message . "\n";

                    $this->incrementDistributedCounter($matchWithResult["id"], -1);
                    $this->fire();
                    return true;
                } else {
                    $event = [];
                    $odd = \App\Models\Events\Odd::where('id', $schedule['odd_id'])->first();

                    $event["homeTeamId"] = $matchWithResult->homeTeamId;
                    $event["awayTeamId"] = $matchWithResult->awayTeamId;
                    $event["eventDate"] = $matchWithResult->eventDate;
                    $event["predictionId"] = $odd->predictionId;

                    $packages = \App\Package::where('siteId', $schedule['siteId'])
                        ->where('tipIdentifier', $schedule['tipIdentifier'])
                        ->get();
                    $eventModel = $this->getOrCreateEvent($event);

                    $this->distributeEvent($eventModel, $packages);
                    
                    $message = "Valid match result for schedule<" . $schedule["id"] . "> | Match<" . $schedule["match_id"] . ">";
                    echo $message . "\n";

                    \App\Models\Autounit\DailySchedule::find($schedule['id'])
                    ->update([
                        'to_distribute' => true,
                        'status' => 'success',
                        'info'   => json_encode(['Eligible event.']),
                    ]);
                    return true;
                }
            }
            
            
            // the auto-unit first checks in the admin-pool list of events
            // if it didn't find any event within that pool, that satisfies the site configuration for the auto-unit
            // it will continue to search within the rest of the events inside the application
            $event = $this->chooseEvent($schedule, $leagueArr, $this->todayAdminPoolEvents);

            if ($event == null) {
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
                $event = $this->chooseEvent($schedule, $leagueArr, $this->todayEvents);
            }

            if ($event == null) {

                // add log if not exists or it is solved
                $checksum = md5($schedule['id'] . $schedule['siteId'] . 'autounit-associated-leagues' . $schedule['tipIdentifier']);
                if (! \App\Models\Log::where('identifier', $checksum)->where('status', 1)->count()) {
                    $site = \App\Site::find($schedule['siteId']);
                    \App\Models\Log::create([
                        'type' => 'warning',
                        'module' => 'autounit',
                        'identifier' => $checksum,
                        'status' => 1,
                        'info' => json_encode(["Site: " . $site->name . " not find any event in associated leagues for tip: " . $schedule['tipIdentifier'] . ", will try to find an event in all leagues"]),
                    ]);
                }

                // try with all leagues
                $event = $this->chooseEvent($schedule, $this->allLeagues, $this->todayEvents);
            }

            if ($event == null) {
                // add log if not exists or it is solved
                $checksum = md5($schedule['id'] . $schedule['siteId'] . 'autounit-all-leagues' . $schedule['tipIdentifier']);
                if (! \App\Models\Log::where('identifier', $checksum)->where('status', 1)->count()) {
                    $site = \App\Site::find($schedule['siteId']);
                    \App\Models\Log::create([
                        'type' => 'panic',
                        'module' => 'autounit',
                        'identifier' => $checksum,
                        'status' => 1,
                        'info' => json_encode(["Site: " . $site->name . " not find any event in all leagues for tip: " . $schedule['tipIdentifier']]),
                    ]);
                }

                \App\Models\Autounit\DailySchedule::find($schedule['id'])
                    ->update([
                        'status' => 'error',
                        'info'   => json_encode(['Not find events in all leagues']),
                    ]);

                continue;
            }

            $info['created']++;

            $eventModel = $this->getOrCreateEvent($event);

            // get all packages according to schedule
            $packages = \App\Package::where('siteId', $schedule['siteId'])
                ->where('tipIdentifier', $schedule['tipIdentifier'])
                ->get();

            if ($event["to_distribute"]) {
                $this->distributeEvent($eventModel, $packages);
                echo "to_distribute_true\n";
                \App\Models\Autounit\DailySchedule::find($schedule['id'])
                ->update([
                    'to_distribute' => true,
                    'status' => 'success',
                    'info'   => json_encode(['Eligible event.']),
                ]);
            } else {
                echo "to_distribute_false\n";
                \App\Models\Autounit\DailySchedule::find($schedule['id'])
                ->update([
                    'status' => 'waiting',
                    'info'   => json_encode(['Ineligible event.']),
                ]);
            }
            $this->incrementDistributedCounter($event["matchId"], 1);
        }

        echo json_encode($info);
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
		}

        return $ev->toArray();
    }

    private function getOrCreateAssociation($event)
    {
        $assoc = \App\Association::where('eventId', $event['id'])
            ->where('type', $event['type'])
            ->where('predictionId', $event['predictionId'])
            ->first();

        if (! $assoc) {
            $event['eventId'] = (int)$event['id'];
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
    private function chooseEvent(array $schedule, array $leagues, array $finishedEvents)
    {
        if (! count($leagues))
            return null;

        $index = rand(0, count($leagues) -1);
        $leagueId = $leagues[$index];

        //  if league not have events today unset current index and reset keys
        if (! array_key_exists($leagueId, $finishedEvents))
            return $this->chooseEvent($schedule, $this->unsetIndex($leagues, $index), $finishedEvents);

        $event = $this->getWinnerEvent($schedule, $finishedEvents[$leagueId]);
 
        //  if not found event unset current index and reset keys
        if ($event == null)
            return $this->chooseEvent($schedule, $this->unsetIndex($leagues, $index), $finishedEvents);

        // check if the match was already distributed in the site
        // a site cannot have the same match distributed twice with different predictions
        if ($this->isMatchDistributed($event, $schedule)) {
            return $this->chooseEvent($schedule, $this->unsetIndex($leagues, $index), $finishedEvents);
        }
        // check if the event was already distributed in a different site
        // events that were not distributed at all
        // or have a lower value than the MAX number of events distributed in a site
        // have a higher priority
        if ($this->isDistributedTooManyTimes($event, $finishedEvents)) {
            return $this->chooseEvent($schedule, $this->unsetIndex($leagues, $index), $finishedEvents);
        }
        echo "Distribute: " . $event["to_distribute"] . "\n";
        $scheduleModel = \App\Models\AutoUnit\DailySchedule::where("id", "=", $schedule["id"])
            ->update([
                "match_id" => $event["primaryId"],
                "to_distribute" => $event["to_distribute"],
                "odd_id" => $event["oddId"]
            ]);
        
        return $event;
    }

    private function getWinnerEvent($schedule, $events)
    {
        if (! count($events))
            return null;

        $index = rand(0, count($events) -1);
        $event = $events[$index];

        // get odds for event
        $odds = \App\Models\Events\Odd::where('matchId', $event['id'])
            ->where('leagueId', $event['leagueId'])
            ->whereIn('predictionId', $this->predictions)
            ->where('odd', '>=', $schedule['minOdd'])
            ->where('odd', '<=', $schedule['maxOdd'])
            ->get()
            ->toArray();

        // Try next event if there is no odds
        if (! count($odds))
            return $this->getWinnerEvent($schedule, $this->unsetIndex($events, $index));

        // try to find correct status base on odd
        foreach ($odds as $odd) {
            $statusByScore = new \App\Src\Prediction\SetStatusByScore($event['result'], $odd['predictionId']);
            $statusByScore->evaluateStatus();
            $statusId = $statusByScore->getStatus();

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
            return $event;
        }

        return $this->getWinnerEvent($schedule, $this->unsetIndex($events, $index));
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
    }

    // @ param int $siteId
    // @ param string $date
    // @ param string $tipIdentifier
    // get associated leagues with tip Identifier
    // @return array()
    private function getAssociatedLeaguesBySchedule($siteId, $date, $tipIdentifier) : array
    {
        return \App\Models\AutoUnit\League::select('leagueId')
            ->where('date', $date)
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
         $events = \App\Match::where('eventDate', 'like', '%' . $this->systemDate . '%')
            ->get()
            ->toArray();

        $adminPoolEvents = AdminPool::getAutoUnitPoolMatches($this->systemDate);

        foreach ($events as $event) {
            $this->todayEvents[$event['leagueId']][] = $event;
        }
        foreach ($adminPoolEvents as $adminPoolEvent) {
            $this->todayAdminPoolEvents[$adminPoolEvent["leagueId"]][] = $adminPoolEvent;
        }
    }

    // get full schedule for today from autounit
    // @return array()
    private function getAutoUnitTodaySchedule() : array
    {
        return \App\Models\AutoUnit\DailySchedule::where('systemDate', $this->systemDate)
            ->where('status', '!=', 'success')
            ->get()
            ->toArray();
    }
    
    private function getAutoUnitFilteredSchedule($date, $matchId) : array
    {
        return \App\Models\AutoUnit\DailySchedule::where('systemDate', $date)
            ->where('status', '=', 'waiting')
            ->where('match_id', '=', $matchId)
            ->get()
            ->toArray();
    }
    
    private function isMatchDistributed(array $event, array $schedule) : bool
    {
        $distributed = \App\Distribution::where("homeTeamId", "=", $event["homeTeamId"])
            ->where("awayTeamId", "=", $event["awayTeamId"])
            ->where("eventDate", "=", $event["eventDate"])
            ->where("siteId", "=", $schedule["siteId"])
            ->exists();

        if ($distributed) {
            return true;
        }
        return false;
    }
    
    private function isDistributedTooManyTimes(array $event, array $finishedEvents) : bool
    {
        $matchIds = [];

        // map the match ids so we can limit the search
        foreach ($finishedEvents as $key => $value) {
            foreach ($value as $finishedEvent) {
                $matchIds[] = $finishedEvent["primaryId"];
            }
        }
        $match = \App\Match::select(
                DB::raw("MAX(sites_distributed_counter) AS max"),
                DB::raw("MIN(sites_distributed_counter) AS min")
            )
            ->whereRaw("DATE_FORMAT(eventDate, '%Y-%m-%d') = '" . $this->systemDate . "'")
            ->whereIn("primaryId", $matchIds)
            ->first();

        if ($event["sites_distributed_counter"] < $match->max || $event["sites_distributed_counter"] == 0 || $match->max == $match->min) {
            return false;
        }
        return true;
    }
    
    private function incrementDistributedCounter(int $matchId , int $value) : void
    {
        $match = \App\Match::where("id", "=", $matchId)->first();
        $match->sites_distributed_counter += $value;
        $match->save();
    }
}

