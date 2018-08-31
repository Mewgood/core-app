<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Events\EventModel;
use App\Match;

class Event extends Controller
{

    public function index()
    {
        return \App\Event::all();
    }

    // @retun object event
    public function get($id) {
        return \App\Event::find($id);
    }

    // get all associated events
    // @return array()
    public function getAssociatedEvents() {

        $eventsIds = [];
        $ids = \App\Association::select('eventId')->distinct()->where('eventId', '!=', '0')->get();

        foreach ($ids as $id)
            $eventsIds[] = $id->eventId;

        $events = \App\Event::whereIn('id', $eventsIds)->get();
        foreach ($events as $event)
            $event->status;

        return $events;
    }

    public function store() {}

    public function update() {}

    public function destroy() {}

    // add event from match
    // @param integer $matchId
    // @param string  $predictionId
    // @param string  $odd
    // @return array()
    public function createFromMatch(Request $r)
    {
        $events = $r->input('events');
        $errors = [];
        $isErrored = false;
        
        foreach ($events as $event) {
            $validMessage = EventModel::validateAddFromMatch($event);
            if ($validMessage["type"] == "error") {
                $isErrored = true;
                $errors[] = $validMessage;
            }
        }
        if ($isErrored) {
            return [
                'type' => 'error',
                'message' => "Failed to insert",
                'data' => $errors
            ];
        }

        foreach ($events as $event) {
            $match = Match::find($event["matchId"]);
            $match = $match->toArray();

            $match['predictionId'] = $event["predictionId"];
            $match['odd'] = number_format((float) $event["odd"], 2, '.', '');
            $match['source'] = 'feed';
            $match['provider'] = 'event';
            $match['matchId'] = $match['id'];

            if ($match['result'] != '') {
                $statusByScore = new \App\Src\Prediction\SetStatusByScore($match['result'], $match['predictionId']);
                $statusByScore->evaluateStatus();
                $statusId = $statusByScore->getStatus();
                $match['statusId'] = $statusId;
            }

            unset($match['id']);
            
            // get the aliases - added by GDM
            $homeTeamAlias = \App\Models\Team\Alias::where('teamId', $match['homeTeamId'] )->first();
            if( $homeTeamAlias && $homeTeamAlias->alias && $homeTeamAlias->alias != '' ) {
                $match['homeTeam'] = $homeTeamAlias->alias;
            }		
            $awayTeamAlias = \App\Models\Team\Alias::where('teamId', $match['awayTeamId'] )->first();
            if( $awayTeamAlias && $awayTeamAlias->alias && $awayTeamAlias->alias != '' ) {
                $match['awayTeam'] = $awayTeamAlias->alias;
            }		
            $leagueAlias = \App\Models\League\Alias::where('leagueId', $match['leagueId'] )->first();
            if( $leagueAlias && $leagueAlias->alias && $leagueAlias->alias != '' ) {
                $match['league'] = $leagueAlias->alias;
            }
            
            $countryAlias = \App\Models\Country\Alias::where('countryCode', $match['countryCode'] )->first();
            if( $countryAlias && $countryAlias->alias && $countryAlias->alias != '' ) {
                $match['country'] = $countryAlias->alias;
            }
            $event = \App\Event::create($match);
            $savedEvents[] = $event;

            $existingOdd = \App\Models\Events\Odd::where('matchId', $event["matchId"])
                ->where('leagueId', $match['leagueId'])
                ->where('predictionId', $event["predictionId"])
                ->first();

            if (! $existingOdd) {
                \App\Models\Events\Odd::create([
                    'matchId' => $event["matchId"],
                    'leagueId' => $match['leagueId'],
                    'predictionId' => $event["predictionId"],
                    'odd' => $event["odd"],
                ]);
            } else {
                // odd already exists , check if it is the same
                if ($existingOdd->odd != $event["odd"]) {

                    // update odd
                    $existingOdd->odd = $event["odd"];
                    $existingOdd->save();
                }
            }
        }

        return [
            'data' => $savedEvents,
            'type' => 'success',
            'message' => "Events were created with success",
        ];
    }

	// add event manually
    // @param integer $country
    // @param integer $league
    // @param integer $homeTeam
    // @param integer $awayTeam
    // @param integer $homeTeamId
    // @param integer $awayTeamId
    // @param integer $leagueId
    // @param integer $countryCode
    // @param integer $eventDate
    // @param string  $predictionId
    // @param string  $odd
    // @return array()
    public function createManual(Request $r)
    {
		// not sure if we should get the country , league and teams data from DB based on ID or directly from the form - currently we get it from the form as it's faster to implement 
        $country = $r->input('country');
        $league = $r->input('league');
        $homeTeam = $r->input('homeTeam');
        $awayTeam = $r->input('awayTeam');
		
        $homeTeamId = $r->input('homeTeamId');
        $awayTeamId = $r->input('awayTeamId');
        $leagueId = $r->input('leagueId');
        $countryCode = $r->input('countryCode');		
        $eventDate = $r->input('eventDate');		
        $predictionId = $r->input('predictionId');		
        $odd = $r->input('odd');

		// Input validations
        if (!$predictionId || trim($predictionId) == '-') {
            return [
                'type' => 'error',
                'message' => "Prediction can not be empty!",
            ];
        }
        if (!$odd || trim($odd) == '-') {
            return [
                'type' => 'error',
                'message' => "Odd can not be empty!",
            ];
        }		
		if (!$homeTeamId || trim($homeTeamId) == '-') {
            return [
                'type' => 'error',
                'message' => "Home Team can not be empty!",
            ];
        }		
		if (!$awayTeamId || trim($awayTeamId) == '-') {
            return [
                'type' => 'error',
                'message' => "Away Team can not be empty!",
            ];
        }		
		if (!$leagueId || trim($leagueId) == '-') {
            return [
                'type' => 'error',
                'message' => "League can not be empty!",
            ];
        }		
		if (!$countryCode || trim($countryCode) == '-') {
            return [
                'type' => 'error',
                'message' => "Country can not be empty!",
            ];
        }
		
		// check if the same team was selected for both away and home team inputs
        if ( $homeTeamId == $awayTeamId ) {
            return [
                'type' => 'error',
                'message' => "You can not select the same team as both away and home teams",
            ];
        }
		
        // check if event already exists with same prediciton
		// we check only the date - not the time of the event
		$checkDate = strtok($eventDate,  ' ');
        if (\App\Event::where('homeTeamId', $homeTeamId)
            ->where('awayTeamId', $awayTeamId)
            // ->where('eventDate', $eventDate)
            ->where('eventDate', 'like' , $checkDate . '%')
            ->where('predictionId', $predictionId)
            ->count())
        {
            return [
                'type' => 'error',
                'message' => "This events already exists with same prediction",
            ];
        }
		
		// get the aliases - added by GDM
		$homeTeamAlias = \App\Models\Team\Alias::where('teamId', $homeTeamId)->first();
		if( $homeTeamAlias && $homeTeamAlias->alias && $homeTeamAlias->alias != '' ) {
			$homeTeam = $homeTeamAlias->alias;
		}		
		$awayTeamAlias = \App\Models\Team\Alias::where('teamId', $awayTeamId)->first();
		if( $awayTeamAlias && $awayTeamAlias->alias && $awayTeamAlias->alias != '' ) {
			$awayTeam = $awayTeamAlias->alias;
		}		
		$leagueAlias = \App\Models\League\Alias::where('leagueId', $leagueId)->first();
		if( $leagueAlias && $leagueAlias->alias && $leagueAlias->alias != '' ) {
			$league = $leagueAlias->alias;
		}
		
		$countryAlias = \App\Models\Country\Alias::where('countryCode', $countryCode )->first();
		if( $countryAlias && $countryAlias->alias && $countryAlias->alias != '' ) {
			$country = $countryAlias->alias;
		}
		
		// prepare the event data
		$eventData = [];
        $eventData['country'] = $country;
        $eventData['league'] = $league;
        $eventData['homeTeam'] = $homeTeam;
        $eventData['awayTeam'] = $awayTeam;
		
        $eventData['result'] = '';
        $eventData['status'] = '';
		
        $eventData['homeTeamId'] = $homeTeamId;
        $eventData['awayTeamId'] = $awayTeamId;
        $eventData['leagueId'] = $leagueId;
        $eventData['countryCode'] = $countryCode;
        $eventData['predictionId'] = $predictionId;
        $eventData['eventDate'] = $eventDate;
        $eventData['odd'] = number_format((float) $odd, 2, '.', '');
        $eventData['source'] = 'manual';
        $eventData['provider'] = 'manual';
        $eventData['matchId'] = 0;
		
        $event = \App\Event::create($eventData);

        return [
            'type' => 'success',
            'message' => "Event was creeated with success",
            'data' => $event,
        ];
		
    }
    
    // add event manually
    // @param array $r Array containing a list of events
    //      @param integer $country
    //      @param integer $league
    //      @param integer $homeTeam
    //      @param integer $awayTeam
    //      @param integer $homeTeamId
    //      @param integer $awayTeamId
    //      @param integer $leagueId
    //      @param integer $countryCode
    //      @param integer $eventDate
    //      @param string  $predictionId
    //      @param string  $odd
    // @return array()
    public function createManualBulk(Request $r)
    {
        $events = [];
        DB::beginTransaction();

        try {
            $events = EventModel::bulkInsert($r->all()["events"]);
            if (isset($events[0]["type"]) && $events[0]["type"] == "error") {
                DB::rollback();
                return [
                    'type' => 'error',
                    'message' => "Failed to insert some events",
                    'data' => $events,
                ];
            }
        } catch(\Exception $e) {
            DB::rollback();

            return [
                'type' => 'error',
                'message' => $e->getMessage() . "\n" . $e->getFile().' on line '. $e->getLine(),
                'data' => $r->all(),
            ];
        }

        DB::commit();

        return [
            'data' => $events,
            'type' => 'success',
            'message' => "Events were created with success"
        ];
    }
    // @param integer $eventId
    // @param string  $result
    // @param integer $statusId
    // @retun array()
    public function updateResultAndStatus($eventId, $result, $statusId) {

        //  TODO check validity of result and status

        $event = \App\Event::find($eventId);
        if (!$event)
            return [
                'type' => 'error',
                'message' => "This event not exist anymore!",
            ];

        // update event
        $event->result = $result;
        $event->statusId = $statusId;
        $event->save();

        $update = [
            'result' => $result,
            'statusId' => $statusId,
        ];

        // update match
        \App\Match::where('id', $event->matchId)
            ->where('leagueId', $event->leagueId)
            ->update([
                'result' => $result,
            ]);

        // update associations
        \App\Association::where('eventId', $eventId)->update($update);

        // update distribution
        \App\Distribution::where('eventId', $eventId)->update($update);

        // update subscriptionTipHistory
        \App\SubscriptionTipHistory::where('eventId', $eventId)->update($update);

        // update archive home
        \App\ArchiveBig::where('eventId', $eventId)->update($update);

        // update update archive big
        \App\ArchiveHome::where('eventId', $eventId)->update($update);

        // process subscriptions
        $subscriptionInstance = new \App\Http\Controllers\Admin\Subscription();
        $subscriptionInstance->processSubscriptions($eventId);

        return [
            'type' => 'success',
            'message' =>"Prediction and status was succesfful updated.",
        ];
    }

    /*
     * @return array()
     */
    public function getTablesFiltersValues($table)
    {
        $data = [
            'tipsters' => [],
            'leagues'  => []
        ];

        if ($table == 'run' || $table == 'ruv') {
            $data['tipsters'] = \App\Event::distinct()->select('provider')
                ->where('eventDate', '>', Carbon::now('UTC')->addMinutes(20))
                ->groupBy('provider')->get();

            $data['leagues'] = \App\Event::distinct()->select('league')
                ->where('eventDate', '>', Carbon::now('UTC')->addMinutes(20))
                ->groupBy('league')->get();
        }

        if ($table == 'nun' || $table == 'nuv') {
            $data['tipsters'] = \App\Event::distinct()->select('provider')
                ->where([
                    ['eventDate', '<', Carbon::now('UTC')->modify('-105 minutes')],
                        ['result', '<>', ''],
                        ['statusId', '<>', '']
                    ])->groupBy('provider')->get();

            $data['leagues'] = \App\Event::distinct()->select('league')
                ->where([
                    ['eventDate', '<', Carbon::now('UTC')->modify('-105 minutes')],
                        ['result', '<>', ''],
                        ['statusId', '<>', '']
                    ])->groupBy('league')->get();
        }

        return $data;
    }

    /*
     * @return int
     */
    public function getNumberOfAvailableEvents(Request $request)
    {
        $nr = \App\Event::where($this->whereForAvailableEvents($request))->count();
        return $nr ? $nr : 0;
    }

    /*
     * @return array()
     */
    public function getAvailableEvents(Request $request)
    {
        return \App\Event::where($this->whereForAvailableEvents($request))->orderBy('eventDate', 'desc')->get();
    }

    /*
     * @return array() of filters for eloquent
     */
    private function whereForAvailableEvents(Request $request)
    {
        $where = [];
        if ($request->get('provider'))
            $where[] = ['provider', '=', $request->get('provider')];

        if ($request->get('league'))
            $where[] = ['league', '=', $request->get('league')];

        if ($request->get('minOdd'))
            $where[] = ['odd', '>=', $request->get('minOdd')];

        if ($request->get('maxOdd'))
            $where[] = ['odd', '<=', $request->get('maxOdd')];

        if ($request->get('table') == 'run' || $request->get('table')== 'ruv')
            $where[] = ['eventDate', '>', Carbon::now('UTC')->addMinutes(20)];

        if ($request->get('table') == 'nun' || $request->get('table') == 'nuv') {
            $where[] = ['eventDate', '<', Carbon::now('UTC')->modify('-105 minutes')];
            $where[] = ['result', '<>', ''];
            $where[] = ['statusId', '<>', ''];
        }

        return $where;
    }
}
