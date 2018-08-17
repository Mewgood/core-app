<?php namespace App\Console\Commands;

class AutoUnitAddEvents extends CronCommand
{
    protected $name = 'autounit:add-events';
    protected $description = 'Add events according to autounit schedule.';

    private $systemDate;
    private $todayFinishedEvents = [];

    private $allLeagues = [];
    private $useAllLeagues = false;

    private $predictions = [];

    private $SiteAssocEvents = [];

    public function fire()
    {
        $cron = $this->startCron();
        $this->systemDate = gmdate('Y-m-d');

        $info = [
            'created' => 0,
            'message' => []
        ];

        // set all leagues
        foreach (\App\League::all() as $l) {
            $this->allLeagues[] = $l->id;
        }

        // get all schedule for today
        $schedules = $this->getAutoUnitTodaySchedule();

        // load today finished events
        $this->setTodayFinishedEvents();

        if (! count($this->todayFinishedEvents)) {

            $info['message'] = 'There is no finished events yet';

            $this->info(json_encode($info));
            $this->stopCron($cron, $info);
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

            $event = $this->chooseEvent($schedule, $leagueArr);

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
                $event = $this->chooseEvent($schedule, $this->allLeagues);
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

            \App\Models\Autounit\DailySchedule::find($schedule['id'])
                ->update([
                    'status' => 'success',
                    'info'   => json_encode(['Event was added with success.']),
                ]);

            $info['created']++;

            $event = $this->getOrCreateEvent($event);

            // get all packages according to schedule
            $packages = \App\Package::where('siteId', $schedule['siteId'])
                ->where('tipIdentifier', $schedule['tipIdentifier'])
                ->get();

            $this->distributeEvent($event, $packages);
        }

        $this->info(json_encode($info));
        $this->stopCron($cron, $info);
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
    private function chooseEvent(array $schedule, array $leagues)
    {
        if (! count($leagues))
            return null;

        $index = rand(0, count($leagues) -1);
        $leagueId = $leagues[$index];

        //  if league not have events today unset current index and reset keys
        if (! array_key_exists($leagueId, $this->todayFinishedEvents))
            return $this->chooseEvent($schedule, $this->unsetIndex($leagues, $index));

        $event = $this->getWinnerEvent($schedule, $this->todayFinishedEvents[$leagueId]);

        //  if not found event unset current index and reset keys
        if ($event == null)
            return $this->chooseEvent($schedule, $this->unsetIndex($leagues, $index));

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

            if ($statusId < 1)
                continue;

            if ($statusId == $schedule['statusId']) {
                $event['matchId'] = $event['id'];
                $event['source'] = 'feed';
                $event['provider'] = 'autounit';
                unset($event['id']);
                unset($event['primaryId']);
                unset($event['created_at']);
                unset($event['updated_at']);
                $event['odd'] = $odd['odd'];
                $event['predictionId'] = $odd['predictionId'];
                $event['statusId'] = $statusId;
                $event['systemDate'] = $this->systemDate;
                return $event;
            }
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

    // set in $this->todayFinishedEvents all events finished today
    // @return void
    private function setTodayFinishedEvents()
    {
         $events = \App\Match::where('eventDate', 'like', '%' . $this->systemDate . '%')
            ->where('result', '<>', '')
            ->get()
            ->toArray();

         foreach ($events as $event) {
             $this->todayFinishedEvents[$event['leagueId']][] = $event;
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
}

