<?php namespace App\Console\Commands;

class DistributionEmailSchedule extends CronCommand
{
    protected $name = 'distribution:pre-send';
    protected $description = 'Associate events with subscriptions and move email content to email_schedule table';

    public function fire()
    {
        $cron = $this->startCron();

        $events = $this->loadData();

        if (!$events) {
            $this->stopCron($cron, []);
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
            // $result = $distributionInstance->associateEventsWithSubscription($gids);
            $result = $distributionInstance->associateEventsWithSubscriptionUpdated($gids);
            $info['message'][] = $result['message'];
            $info['scheduled'] = $info['scheduled'] + count($gids);
        }

        $this->info(json_encode($info));
        $this->stopCron($cron, $info);
        return true;
    }

    protected function loadData()
    {
        return \App\Distribution::where('isEmailSend', '0')
            ->whereNotNull('mailingDate')
            ->where('mailingDate', '<=', gmdate('Y-m-d H:i:s'))
            ->get();
    }
}

