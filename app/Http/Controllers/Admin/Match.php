<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;

class Match extends Controller
{

    public function index()
    {
    }

    // get match by id
    // @param integer $id
    // @return object
    public function get($id)
    {
        return \App\Match::find($id);
    }

    // get all available matches by search
    // @param string $filter
    // @param string $table
    // @return array()
    public function getMatchesByFilter($table, $filter, $date = null )
    {
		
		
        $filter = trim(urldecode($filter));

        $events = \App\Match::where('country', 'like', '%' . $filter . '%')
            ->orWhere('league', 'like', '%' . $filter . '%')
            ->orWhere('homeTeam', 'like', '%' . $filter . '%')
            ->orWhere('awayTeam', 'like', '%' . $filter . '%')
            ->orderBy('eventDate', 'asc')->get();

        if ($table == 'run' || $table == 'ruv') {
            foreach ($events as $k => $v) {
				// unset the events that don't match the given date
				if( !is_null($date) ) {
					if( Carbon::parse($v->eventDate)->startOfDay() != Carbon::parse($date)->startOfDay() ) {
						unset($events[$k]);
					}
				}

                // unset events starts less than 20 minutes
                if ($v->eventDate < Carbon::now('GMT')->addMinutes(20))
                    unset($events[$k]);

                // unset events starts less than 20 minutes
                if ($v->eventDate > Carbon::now('GMT')->addDays(3))
                    unset($events[$k]);
            }
            return $events;
        }

        // prepare events for nun || nuv
        foreach ($events as $k => $v) {
			
			// unset the events that don't match the given date
			if( !is_null($date) ) {
				if( Carbon::parse($v->eventDate)->startOfDay() != Carbon::parse($date)->startOfDay() ) {
					unset($events[$k]);
				}
			}
				
            // unset events finished less than 105 minutes
            if ($v->eventDate > Carbon::now('UTC')->modify('-105 minutes'))
                unset($events[$k]);

            // unset events with no result and status
            if (! $v->result)
                unset($events[$k]);
        }
        return $events;
    }

    public function getLeagueMatches(Request $request)
    {
        $matches = \App\Match::getLeagueMatches($request->leagueIds, $request->date);
        return response($matches, 200);
    }

    public function store() {}

    public function update() {}

    public function destroy() {}

}
