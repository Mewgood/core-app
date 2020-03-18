<?php

namespace App\Console\Commands;

use App\Match;
use App\Prediction;
use Nathanmac\Utilities\Parser\Facades\Parser;

class UpdateOdd extends CronCommand
{
    protected $name = 'odds:update';
    protected $description = 'Will update the odds for every match and replace AU matches where the odd does not respect the conditions set';

    public function fire()
    {
        $predictions = [];
        foreach (Prediction::all() as $pred) {
            $predictions[$pred->identifier] = true;
        }
        $now = date("Y-m-d H:i:s");
        $today = date("Y-m-d");
        $matches = Match::with("odds")
            ->where("eventDate", ">=", $now)
            ->whereRaw("DATE_FORMAT(eventDate, '%Y-%m-%d') = '" . $today . "'")
            ->get();

        foreach ($matches as $match) {
            $url = env("LINK_PORTAL_EVENT_RESULT") . "?tournament=" . $match->leagueId . "&" . "match=" . $match->id;
            $xml = file_get_contents($url);

            if (!$xml) {
                continue;
            }
            $parsedXml = Parser::xml($xml);
            
            if (!empty($parsedXml["odds"]["odd"])) {
                foreach ($parsedXml["odds"]["odd"] as $feedOdd) {
                    $predictionId = $this->checkOdd($predictions, $feedOdd);

                    if ($predictionId) {
                        foreach ($match->odds as $odd) {
                            if ($odd->predictionId == $predictionId) {
                                // TO DO: check for AU interval and replace match if is not good for it
                                $odd->odd = $this->roundOdds($feedOdd["value"]);
                                $odd->update();

                                if ($match->events) {
                                    $match->events()
                                        ->where("predictionId", "=", $predictionId)
                                        ->update([
                                            "odd" => $odd->odd
                                        ]);
                                    foreach ($match->events as $event) {
                                        $sentEmail = $event->distributions()->where("isEmailSend", "=", 1)->exists();
                                        if (!$sentEmail) {
                                            if ($event->distributions) {
                                                $event->distributions()
                                                    ->where("predictionId", "=", $predictionId)
                                                    ->update([
                                                        "odd" => $odd->odd
                                                    ]);
                                            }
        
                                            if ($event->associations) {
                                                $event->associations()
                                                    ->where("predictionId", "=", $predictionId)
                                                    ->update([
                                                        "odd" => $odd->odd
                                                    ]);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    private function checkOdd($predictions, $odd)
    {
        $predictionId = "";
        if ($odd['type'] == 'total') {
            $predictionId = strtolower($odd['element']) . '_' . $odd['typekey'];
        }

        // ah
        if ($odd['type'] == 'asian_handicap') {
            $predictionId = ($odd['element'] == 'Home') ? 'team1-ah_' : 'team2-ah_';

            if ($odd['typekey'][0] == '-' || $odd['typekey'] == '0')
                $predictionId .= trim($odd['typekey']);
            else
                $predictionId .= '+' . trim($odd['typekey']);
        }

        //g/g
        if ($odd['type'] == 'goal_nogoal') {
            $predictionId = $odd['element'] == 'Yes' ? 'bothToScore' : 'noGoal';
        }

        //1x2 HO -> homeTeam | AO -> awayTeam | DO -> equal
        if ($odd['type'] == '3W') {

            if ($odd['element'] == 'HO')
                $predictionId = 'team1';
            elseif ($odd['element'] == 'AO')
                $predictionId = 'team2';
            else
                $predictionId = 'equal';
        }

        if (!isset($predictions[$predictionId])) {
           return false;
        }
        return $predictionId;
    }

    private function roundOdds($odd) {
        $stringOdd = (string)$odd;
        $firstDecimal = (int)substr($stringOdd, 2, strlen($stringOdd) - 1);
        $lastDecimal = (int)substr($stringOdd, 3, strlen($stringOdd));
        
        if ($lastDecimal == 0) {
            return (float)$stringOdd;
        } elseif ($lastDecimal < 3) {
            $lastDecimal = 0;
        } elseif ($lastDecimal >= 3 && $lastDecimal < 8) {
            $lastDecimal = 5;
        } elseif ($lastDecimal >= 8) {
            $firstDecimal = 1 + (int)$firstDecimal;
            $lastDecimal = $firstDecimal . "0";
        }
        $stringOdd = substr_replace($stringOdd, $lastDecimal, strlen($stringOdd) - strlen($lastDecimal), strlen($stringOdd));
        return (float)$stringOdd;
    }
}