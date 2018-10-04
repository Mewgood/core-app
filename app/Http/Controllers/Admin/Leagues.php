<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Nathanmac\Utilities\Parser\Facades\Parser;

class Leagues extends Controller
{

    public function index()
    {
    }
    
	/* Returns a list of all countries in the DB table */
	public function getAllCountries() {
		// $countries = \App\Country::get();
		$countries = \App\Country::select( [ 'country.code' , \DB::raw( " if( `country_alias`.`alias` is not null , `country_alias`.`alias` , `country`.`name` ) as name ") ] )
			->leftJoin("country_alias", function($leftJoin) {
                $leftJoin->on('country_alias.countryCode', '=', 'country.code');
            })
			->get();
		
		return $countries;
	}
	
	/* Returns all leagues for a given country */
	public function getCountryLeagues( $countryCode ) {
		
		// $leagues = \App\League::select( 'league.*' )
		$leagues = \App\League::select( [ 'league.id' , \DB::raw( " if( `league_alias`.`alias` is not null , `league_alias`.`alias` , `league`.`name` ) as name ") ] )
			->join("league_country", function($join) {
                $join->on('league_country.leagueId', '=', 'league.id');
            })
			->leftJoin("league_alias", function($leftJoin) {
                $leftJoin->on('league_alias.leagueId', '=', 'league.id');
            })
			->where('league_country.countryCode','=',$countryCode)
			->get();
		
		return $leagues;
	}
    
    /* Returns all leagues for a given country list */
	public function getCountryListLeagues(Request $request) {
        $leagues = \App\League::select( 
                [ 'league.id' , 
                \DB::raw( " if( `league_alias`.`alias` is not null , `league_alias`.`alias` , `league`.`name` ) as name ") ] 
            )
            ->join("league_country", "league_country.leagueId", "league.id")
            ->leftJoin("league_alias", "league_alias.leagueId", "league.id")
            ->whereIn('league_country.countryCode', $request->countryCodes)
            ->get();
        
        return $leagues;
    }
	
	/* Returns all teams from a given league - if provided , it will not return a given team */
	public function getLeagueTeams( $league , $exclude = null ) {
		// $teams = \App\Team::select('team.*')
		$teams = \App\Team::select( [ 'team.id' ,  \DB::raw( " if( `team_alias`.`alias` is not null , `team_alias`.`alias` , `team`.`name` ) as name ") ] )
			->join("import_teams_leagues", function($join) {
                $join->on('import_teams_leagues.team_id', '=', 'team.id');
            })
			->leftJoin("team_alias", function($leftJoin) {
                $leftJoin->on('team_alias.teamId', '=', 'team.id');
            })
			->where('import_teams_leagues.league_id','=',$league)
			->get();
		
		return $teams;
	}
	
	/* Returns all teams from a given country */
	public function getCountryTeams( $country_code ) {
		$teams = \App\Team::select('team.*')
			->join("team_country", function($join) {
                $join->on('team_country.teamId', '=', 'team.id');
            })
			->where('team_country.country_code','=',$countryCode)
			->get();
		
		return $teams;
	}
	
	
	public function importTeamsFeed() {
		set_time_limit(0);
		 
		// load xml file
		$feed_url = 'http://tipstersportal.com/feed/full_teams.php';
        // $xml = file_get_contents( $feed_url );

        // parse xml content
        // $feed_response = Parser::xml($xml);
		
		// echo "<pre>";
		// die(print_R($feed_response));
		
		// $file = '/root/projects/core-app/public/logo/feed.txt';
		// $file = '/root/projects/core-app/setup.txt';
		// $content = json_encode($feed_response);
		// file_put_contents($file, $content);
		// $content = json_decode(file_get_contents($file), TRUE);
		// $feed_response = json_decode($this->json, TRUE);
		
		echo "<pre>";
		die(print_R($feed_url));
		
		$countries_array = [];
		$leagues_array = [];
		$teams_array = [];
		
		foreach ($feed_response['country'] as $c_index => $country) {
			$country_data = [];
			$country_data['name'] = $country['name'];
			$country_data['country_code'] = $country['code'];
			$countries_array[] = $country_data;
			
			/*
			$chars = [ 'A' , 'B' , 'C' , 'D', 'E' , 'F' , 'G' ,'H' , 'I', 'J', 'K' , 'L', 'M', 'N' , 'O' , 'P' , 'Q' , 'R' , 'S' , 'T','U', 'V' ];
			$first_letter = substr($country['name'], 0, 1);
			if( in_array($first_letter , $chars )  ) {
				continue;
			}
			*/
			
			// Make sure the country has not been imported already
			$found = \App\Models\ImportFeed\ImportCountries::where('country_code','=',$country_data['country_code'])->where('name','=',$country_data['name'])->first();
			
			if( !$found ) {			
				\App\Models\ImportFeed\ImportCountries::create([
					'country_code' => $country_data['country_code'],
					'name' => $country_data['name']
				]);
			}
						
			if( isset($country['tournaments']) && isset($country['tournaments']['tournament']) ) {
				
				// check for countries with only one tournament
				if( isset($country['tournaments']['tournament']['id']) ) {
					$tournament = $country['tournaments']['tournament'];
					$league_data = [];
					$league_data['league_id'] = $tournament['id'];
					$league_data['title'] = $tournament['title'];
					$league_data['country_code'] = $country['code'];
					$leagues_array[] = $league_data;
					
					// Make sure the country has not been imported already
					$found = \App\Models\ImportFeed\ImportLeagues::where('league_id','=',$league_data['league_id'])
						->where('title','=',$league_data['title'])
						->where('country_code','=',$league_data['country_code'])
						->first();
					
					if( !$found ) {	
						\App\Models\ImportFeed\ImportLeagues::create([
							'league_id' => $league_data['league_id'],
							'title' => $league_data['title'],
							'country_code' => $league_data['country_code']
						]);
					}
					
					$league_to_country_data = [
						'league_id' => $league_data['league_id'] ,
						'country_code' => $league_data['country_code'] 
					];
					
					// Make sure the country has not been imported already
					$found = \App\Models\ImportFeed\ImportLeaguesCountries::where('league_id','=',$league_data['league_id'])->where('country_code','=',$league_data['country_code'])->first();
					
					if( !$found ) {	
						\App\Models\ImportFeed\ImportLeaguesCountries::create([
							'league_id' => $league_data['league_id'],
							'country_code' => $league_data['country_code']
						]);
					}
							
							
					if( isset($tournament['teams']) && isset($tournament['teams']['team']) ) {
						foreach($tournament['teams']['team'] as $team ) {
							$team_data = [];
							$team_data['team_id'] = $team['id'];
							$team_data['title'] = $team['title'];
							$team_data['league_id'] = $tournament['id'];
							$teams_array[] = $team_data;
							
							// Make sure the team has not been imported already
							$found = \App\Models\ImportFeed\ImportTeams::where('team_id','=',$team_data['team_id'])->where('title','=',$team_data['title'])->first();
							
							if( !$found ) {
								\App\Models\ImportFeed\ImportTeams::create([
									'team_id' => $team_data['team_id'],
									'title' => $team_data['title']
								]);
							}
							
							$team_to_league_data = [
								'league_id' => $tournament['id'] ,
								'team_id' => $team['id'] 
							];
							
							$found = \App\Models\ImportFeed\ImportTeamsLeagues::where('league_id','=',$team_to_league_data['league_id'])->where('team_id','=',$team_to_league_data['team_id'])->first();
					
							if( !$found ) {	
								\App\Models\ImportFeed\ImportTeamsLeagues::create([
									'league_id' => $team_to_league_data['league_id'],
									'team_id' => $team_to_league_data['team_id']
								]);
							}
							
						}
					}
						
				} else {
					foreach($country['tournaments']['tournament'] as $tournament ) {
						$league_data = [];
						$league_data['league_id'] = $tournament['id'];
						$league_data['title'] = $tournament['title'];
						$league_data['country_code'] = $country['code'];
						$leagues_array[] = $league_data;
						
						// Make sure the country has not been imported already
						$found = \App\Models\ImportFeed\ImportLeagues::where('league_id','=',$league_data['league_id'])
							->where('title','=',$league_data['title'])
							->where('country_code','=',$league_data['country_code'])
							->first();
						
						if( !$found ) {	
							\App\Models\ImportFeed\ImportLeagues::create([
								'league_id' => $league_data['league_id'],
								'title' => $league_data['title'],
								'country_code' => $league_data['country_code']
							]);
						}
						
						$league_to_country_data = [
							'league_id' => $league_data['league_id'] ,
							'country_code' => $league_data['country_code'] 
						];
						
						// Make sure the country has not been imported already
						$found = \App\Models\ImportFeed\ImportLeaguesCountries::where('league_id','=',$league_data['league_id'])->where('country_code','=',$league_data['country_code'])->first();
						
						if( !$found ) {	
							\App\Models\ImportFeed\ImportLeaguesCountries::create([
								'league_id' => $league_data['league_id'],
								'country_code' => $league_data['country_code']
							]);
						}
								
								
						if( isset($tournament['teams']) && isset($tournament['teams']['team']) ) {
							foreach($tournament['teams']['team'] as $team ) {
								$team_data = [];
								$team_data['team_id'] = $team['id'];
								$team_data['title'] = $team['title'];
								$team_data['league_id'] = $tournament['id'];
								$teams_array[] = $team_data;
								
								// Make sure the team has not been imported already
								$found = \App\Models\ImportFeed\ImportTeams::where('team_id','=',$team_data['team_id'])->where('title','=',$team_data['title'])->first();
								
								if( !$found ) {
									\App\Models\ImportFeed\ImportTeams::create([
										'team_id' => $team_data['team_id'],
										'title' => $team_data['title']
									]);
								}
								
								$team_to_league_data = [
									'league_id' => $tournament['id'] ,
									'team_id' => $team['id'] 
								];
								
								$found = \App\Models\ImportFeed\ImportTeamsLeagues::where('league_id','=',$team_to_league_data['league_id'])->where('team_id','=',$team_to_league_data['team_id'])->first();
						
								if( !$found ) {	
									\App\Models\ImportFeed\ImportTeamsLeagues::create([
										'league_id' => $team_to_league_data['league_id'],
										'team_id' => $team_to_league_data['team_id']
									]);
								}
								
							}
						}
				
					}				
				}				
			}
			
		}
		
        echo " ------------------------------------------------------ <br/> ";
        echo 'Total countries: ' . count($countries_array) . "</br>";
        echo 'Total leagues: ' . count($leagues_array) . "</br>";
        echo 'Total teams: ' . count($teams_array) . "</br>";
        echo " ------------------------------------------------------ <br/> ";
		die(print_R($feed_response));
		die('done');
        
	}
	
}
