<?php 

namespace App\Console\Commands;

use Carbon\Carbon;

class DistributionEmailSchedule extends CronCommand
{
    protected $name = 'distribution:pre-send';
    protected $description = 'Associate events with subscriptions and move email content to email_schedule table';

    public function fire()
    {
        $events = $this->loadData();

        if (!$events) {
            return true;
        }
        $info = [
            'scheduled' => 0,
            'message' => []
        ];

        $group = [];
        foreach ($events as $e) {
            $group[$e->packageId][] = $e->id;
        }

        foreach ($group as $gids) {
            $distributionInstance = new \App\Http\Controllers\Admin\Distribution();
            $result = $distributionInstance->associateEventsWithSubscriptionUpdated($gids);
            $info['message'][] = $result['message'];
            $info['scheduled'] = $info['scheduled'] + count($gids);
        }

        $this->info(json_encode([
            "info" => $info, 
            "message" => "Email where scheduled",
            "date" => gmdate('Y-m-d H:i:s')
        ]));

        return true;
    }

    protected function loadData()
    {
        return \App\Distribution::where('isEmailSend', '0')
            ->whereNotNull('mailingDate')
            ->where('mailingDate', '<=', gmdate('Y-m-d H:i:s'))
            ->where('eventDate', '>=', Carbon::now('UTC')->addMinutes(5))
            ->where('to_distribute', '=', 1)
            ->get();
    }
}

