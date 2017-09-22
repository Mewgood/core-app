<?php

namespace App\Http\Controllers\Cron;

use App\Http\Controllers\Controller;
use Nathanmac\Utilities\Parser\Facades\Parser;

class Portal extends Controller
{

    // this will add new events in match table.
    public function newEvents() {

        $rootDir =  dirname(__DIR__);

        // load xml file
        $xml = file_get_contents(env('LINK_PORTAL_NEW_EVENTS'));

        // parse xml content
        $c = Parser::xml($xml);

        // iterate matches
        $count = 0;
        $eventExists = 0;
        foreach ($c['match'] as $k => $match) {

            // check if event already are imported
            if (\App\Match::find($match['id'])) {
                $eventExists++;
                continue;
            }

            // create array
            $m = [
                'id' => $match['id'],
                'country' => $match['tournament_country'],
                'countryCode' => $match['tournament_country_code'],
                'league' => $match['tournament_title'],
                'leagueId' => $match['tournament_id'],
                'homeTeam' => $match['home_team_name'],
                'homeTeamId' => $match['home_team_id'],
                'awayTeam' => $match['away_team_name'],
                'awayTeamId' => $match['away_team_id'],
                'result' => '',
                'eventDate' => $match['utc_date'],
            ];

            // store country name and code if not exists
            if(!\App\Country::where('code', $m['countryCode'])->first()) {

                if (!$m['countryCode']) {
                    echo "Missing country code for matchId: " . $m['id'] . "<br/>";
                    continue;
                }

                \App\Country::create([
                    'code' => $m['countryCode'],
                    'name' => $m['country']
                ]);
            }

            // do not store local logos.
            //if(!file_exists($rootDir . '/public/logo/country/' . $m['countryCode'] . '.png')) {
            //    $content = @file_get_contents($match['tournament_country_icon']);
            //    file_put_contents($rootDir . '/public/logo/country/' . $m['countryCode'] . '.png', $content);
            //}

            // store league if not exist
            if(!\App\League::find($m['leagueId'])) {
                \App\League::create([
                    'id' => $m['leagueId'],
                    'name' => $m['league']
                ]);
            }

            // store homeTeam if not exists
            if(!\App\Team::find($m['homeTeamId'])) {
                \App\Team::create([
                    'id' => $m['homeTeamId'],
                    'name' => $m['homeTeam'],
                ]);
            }

            // do not store local logos.
            //if(!file_exists($rootDir . '/public/logo/team/' . $m['homeTeamId'] . '.png')) {
            //    $content = @file_get_contents($match['home_team_logo']);
            //    file_put_contents($rootDir . '/public/logo/team/' . $m['homeTeamId'] . '.png', $content);
            //}

            // store awayTeam if not exists
            if(!\App\Team::find($m['awayTeamId'])) {
                \App\Team::create([
                    'id' => $m['awayTeamId'],
                    'name' => $m['awayTeam'],
                ]);
            }

            // do not store local logos.
            //if(!file_exists($rootDir . '/public/logo/team/' . $m['awayTeamId'] . '.png')) {
            //    $content = @file_get_contents($match['away_team_logo']);
            //    file_put_contents($rootDir . '/public/logo/team/' . $m['awayTeamId'] . '.png', $content);
            //}

            // store new match
            \App\Match::create($m);

            $count++;
        }

        echo " ------------------------------------------------------ <br/> ";
        echo 'Total events: ' . count($c['match']) . "</br>";
        echo 'Already Exists: ' . $eventExists . "</br>";
        echo 'Process Events: ' . $count . "</br>";
    }
}
