<?php namespace App\Console\Commands;

use Nathanmac\Utilities\Parser\Facades\Parser;
use App\Console\Commands\AutoUnitAddEvents;
use Illuminate\Support\Facades\DB;

// statuses for tipstersportal can be:
//     canceled, postponed, pending, final
class SetResultAndStatus extends CronCommand
{
    protected $name = 'events:set-result';
    protected $description = 'This will will get result from portal and evaluate stausId';

    public function fire()
    {
        // TESTING MANUAL SETTING A MATCH RESULT
/*
        $match = \App\Match::where("primaryId", "=", 94387)->first();
        
        $events = \App\Event::where('matchId', $match->id)
                    ->where('leagueId', $match->leagueId)
                    ->join("prediction", "prediction.identifier", "event.predictionId")
                    ->get();

        $matchPredictionResults = [];
        $i = 0;

        foreach ($events as $event) {
            $statusByScore = new \App\Src\Prediction\SetStatusByScore($match->result, $event->predictionId);
            $statusByScore->evaluateStatus();
            $statusId = $statusByScore->getStatus();
            
            if ($statusId > 0) {
                $eventInstance = new \App\Http\Controllers\Admin\Event();
                $eventInstance->updateResultAndStatus($event->id, $match->result, $statusId);
                $matchPredictionResults[$i]["predictionName"] = $event->predictionId;
                $matchPredictionResults[$i]["value"] = $statusId;
                $i++;
            }
        }

        $match->prediction_results = json_encode($matchPredictionResults);
        
        $match->save();
        $autoUnitCron = new AutoUnitAddEvents();
        $autoUnitCron->fire($match);
        
        die("RESULT SET");
*/
        //$cron = $this->startCron();
        $info = [
            'appEventNoResult' => 0,
            'processed'        => 0,
            'notFound'         => 0,
            'pending'          => 0,
            'message'          => []
        ];

        $today = strtotime("now");
        $yesterday = strtotime('-1 day', $today);

        $matches = \App\Match::select(
                DB::raw("concat(leagueId, '') AS tournamentId"),
                DB::raw("concat(id, '') AS matchId")
            )
            ->where('result', '')
            ->where('estimated_finished_time', '<=' , gmdate('Y-m-d H:i:s'))
            ->where('eventDate', '>=' , gmdate('Y-m-d H:i:s', $yesterday))
            ->get()
            ->toArray();
            
        $items = json_encode($matches);
        $info['appEventNoResult'] = count($matches);
        $url = env('LINK_PORTAL_LIST_EVENT_RESULTS') . "?items=" . $items;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE); 
        $xml = curl_exec($ch); 
            
        if (!$xml) {
            $info['message'] = "Can not parse xml for machId: " . $match->id . ", leagueId: " . $match->leagueId;
            $this->info(json_encode($info));
            return false;
        }

        $list = Parser::xml($xml);
        
        foreach ($list as $c) {
            if (!isset($c['match_status'])) {
                $info['message'] = "Not found machId";
                $info['notFound']++;
                continue;
            }

            if (trim($c['match_status']) == 'pending') {
                $newEstimatedTime = strtotime($match->estimated_finished_time) + (60 * 40); // add another 40 minutes if the match entered in overtime
                $match->estimated_finished_time = gmdate("Y-m-d H:i:s", $newEstimatedTime);
                $match->save();
                $info['pending']++;
                continue;
            }

            if (trim($c['match_status']) == 'postponed') {
                continue;
            }
            if (trim($c['match_status']) == 'canceled') {
                continue;
            }

            if (trim($c['match_status']) == 'final') {
                $score = str_replace(':', '-', trim($c['fulltime_score']));

                $events = \App\Event::where('matchId', $match->id)
                    ->where('leagueId', $match->leagueId)
                    ->join("prediction", "prediction.identifier", "event.predictionId")
                    ->get();

                $matchPredictionResults = [];
                $i = 0;

                foreach ($events as $event) {
                    $statusByScore = new \App\Src\Prediction\SetStatusByScore($score, $event->predictionId);
                    $statusByScore->evaluateStatus();
                    $statusId = $statusByScore->getStatus();
                    
                    if ($statusId > 0) {
                        $eventInstance = new \App\Http\Controllers\Admin\Event();
                        $eventInstance->updateResultAndStatus($event->id, $score, $statusId);
                        $matchPredictionResults[$i]["predictionName"] = $event->predictionId;
                        $matchPredictionResults[$i]["value"] = $statusId;
                        $i++;
                    }
                }

                $match->result = $score;
                $match->prediction_results = json_encode($matchPredictionResults);

                $match->update();
                $autoUnitCron = new AutoUnitAddEvents();
                $autoUnitCron->fire($match);                

                $info['processed']++;
                echo $score;
            }
        }

        $this->info(json_encode($info));
        //$this->stopCron($cron, $info);
        return true;
    }
}

