<?php namespace App\Console\Commands;

use Nathanmac\Utilities\Parser\Facades\Parser;

// statuses for tipstersportal can be:
//     canceled, postponed, pending, final
class SetResultAndStatus extends CronCommand
{
    protected $name = 'events:set-result';
    protected $description = 'This will will get result from portal and evaluate stausId';

    public function fire()
    {
        $cron = $this->startCron();
        $info = [
            'processed' => 0,
            'notFound'  => 0,
            'pending'   => 0,
            'message'   => []
        ];

        $matches = \App\Match::where('result', '')
            ->where('eventDate', '<' , gmdate('Y-m-d H:i:s', time() - (105 * 60)))
            ->get();

        echo count($matches);

        foreach ($matches as $match) {

            $xml = file_get_contents(env('LINK_PORTAL_EVENT_RESULT') . '?tournament=' . $match->leagueId . '&match=' . $match->id);
            if (!$xml) {
                $info['message'] = "Can not parse xml for machId: " . $match->id . ", leagueId: " . $match->leagueId;
                continue;
            }

            $c = Parser::xml($xml);

            if (!isset($c['match_status'])) {
                $info['message'] = "Not found machId: " . $match->id . ", leagueId: " . $match->leagueId;
                $info['notFound']++;
                continue;
            }

            if (trim($c['match_status']) == 'pending') {
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
                    ->get();

                foreach ($events as $event) {
                    $statusByScore = new \App\Src\Prediction\SetStatusByScore($score, $event->predictionId);
                    $statusByScore->evaluateStatus();
                    $statusId = $statusByScore->getStatus();

                    if ($statusId > 0) {
                        $eventInstance = new \App\Http\Controllers\Admin\Event();
                        $eventInstance->updateResultAndStatus($event->id, $score, $statusId);
                    }
                }

                $match->result = $score;
                $match->update();

                $info['processed']++;
                /* echo $score; */
            }
        }

        $this->info(json_encode($info));
        $this->stopCron($cron, $info);
        return true;
    }
}

