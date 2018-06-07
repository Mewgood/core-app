<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;

class Leagues extends Controller
{

    public function index()
    {
    }
    
	/* Returns a list of all countries in the DB table */
	public function getAllCountries() {
		$countries = \App\Country::get();
		
		return $countries;
	}
	
	/* Returns all leagues for a given country */
	public function getCountryLeagues( $countryCode ) {
		
		$leagues = \App\League::select('league.*')
			->join("league_country", function($join) {
                $join->on('league_country.leagueId', '=', 'league.id');
            })
			->where('league_country.countryCode','=',$countryCode)
			->get();
		
		return $leagues;
	}
	
	/* Returns all teams from a given league - if provided , it will not return a given team */
	public function getLeagueTeams( $league , $exclude = null ) {
		$teams = \App\Team::select('team.*')
			->join("team_country", function($join) {
                $join->on('team_country.teamId', '=', 'team.id');
            })
			->where('team_country.countryCode','=',$countryCode)
			->get();
		
		return $teams;
	}
	
	
}
