<?php

namespace App\Console\Commands;

use App\Models\SubscriptionAlert;

class ProcessSubscriptions extends CronCommand
{
    protected $name = 'subscription:process';

    public function fire()
    {
        $date = gmdate('Y-m-d', strtotime('-1day'));

        // get all active subscriptions type days
        $subscriptions = \App\Subscription::where('status', 'active')
            ->where('type', 'days')->get();

        foreach ($subscriptions as $subscription) {

            // for each subscription get events from yesterday
            $countEvents = \App\SubscriptionTipHistory::where('systemDate', $date)
                ->where('subscriptionId', $subscription->id)->count();

            // add noTip event if user not receive any event today
            if (! $countEvents)
                $this->addNoTipToSubscriptionTipHistory($subscription, $date);


            // get suubscriptionTipHistory events systemDate === today GMT
            $events = \App\SubscriptionTipHistory::where('systemDate', $date)
                ->where('subscriptionId', $subscription->id)->get();

            // if events already processSubscription continue
            if ($events[0]->processSubscription)
                continue;

            // vip subscriptions not care about results
            // also noTip not give them one day
            if ($subscription->isVip) {
                foreach ($events as $event) {
                    $event->processSubscription = '1';
                    $event->processType = '0';
                    $event->update();
                }
                $this->archiveSubscription($subscription, $date);
                continue;
            }

            // check events status
            $haveNoTip = false;
            $haveWinLossDraw = false;
            $allEventsFinished = true;
            foreach ($events as $event) {

                if ($event->isNoTip)
                   $haveNoTip = true;

                if ((int) $event->statusId === 1 || (int) $event->statusId === 2 || (int) $event->statusId === 3)
                    $haveWinLossDraw = true;

                if (trim($event->statusId) === '' && !$event->isNoTip)
                    $allEventsFinished = false;
            }

            // process on win loss draw
            if ($haveWinLossDraw) {
                foreach ($events as $event) {
                    $event->processSubscription = '1';
                    $event->processType = '0';
                    $event->update();
                }

                $this->archiveSubscription($subscription, $date);
                continue;
            }

            // there is events and all finished
            // there is no win, loss, draw
            // only postp  or noTip - add one day
            if ($allEventsFinished) {

                $s = new \App\Subscription();
                $s->parentId = $subscription->id;
                $s->name = $subscription->name;
                $s->subscription = '1';
                $s->type = $subscription->type;
                $s->customerId = $subscription->customerId;
                $s->siteId = $subscription->siteId;
                $s->packageId = $subscription->packageId;
                $s->isCustom = $subscription->isCustom;
                $s->isVip = $subscription->isVip;
                $s->status = 'waiting';

                // if user not have any more subscription on package activate this
                if (! \App\Subscription::where('customerId', $subscription->customerId)
                    ->where('packageId', $subscription->packageId)
                    ->where('status', 'active')->count())
                {
                    $s->status = 'active';
                    $s->dateStart = gmdate('Y-m-d');
                    $s->dateEnd = gmdate('Y-m-d');
                }

                $s->save();

                foreach ($events as $event) {
                    $event->processSubscription = '1';
                    $event->processType = $s->id;
                    $event->update();
                }
            }
            $this->archiveSubscription($subscription, $date);
        }

        // activate new subscriptions
        $waitingSubscriptions = \App\Subscription::where('status', 'waiting')
            ->where('type', 'days')
            ->get();

        foreach ($waitingSubscriptions as $ws) {
            // if user already have active subscription on same package
            // set status waiting
            if (\App\Subscription::where('customerId', $ws->customerId)
                ->where('packageId', $ws->packageId)
                ->where('status', 'active')->count())
            {
                continue;
            }

            $ws->status = 'active';
            $ws->dateStart = gmdate('Y-m-d');
            $ws->dateEnd = gmdate('Y-m-d', strtotime('+ ' . $ws->subscription . ' day'));
            $ws->update();
        }

        $packages = \App\Package::all();
        foreach ($packages as $package) {
            // Evaluate packages and change section if need
            $packageInstance = new \App\Http\Controllers\Admin\Package();
            $packageInstance->evaluateAndChangeSection($package->id);
        }
        $this->processAutounitPauseState();
    }
    
    // @param \App\Subscription $subscription
    // @param string $systemDate
    // this will add an noTip event
    // @return void
    private function addNoTipToSubscriptionTipHistory($subscription, $systemDate)
    {
        \App\SubscriptionTipHistory::create([
            'subscriptionId' => $subscription->id,
            'customerId' => $subscription->customerId,
            'siteId' => $subscription->siteId,
            'isCustom' => $subscription->isCustom,
            'type' => $subscription->type,
            'isNoTip' => '1',
            'isVip' => $subscription->isVip,
            'systemDate' => $systemDate,
        ]);
    }

    // @param \App\Subscription $subscription
    // @param $date
    // this will archive a subscription
    // if endDate is greater or equal to $date
    // @return void
    private function archiveSubscription($subscription, $date)
    {
        if (strtotime($subscription->dateEnd) <= strtotime($date)) {
            $subscription->status = 'archived';
            $subscription->update();
        }
    }
    
    private function processAutounitPauseState()
    {
        $today = gmdate("Y-m-d");
        $packages = \App\Package::select(
                "package.id", 
                "package.siteId",
                "package.paused_autounit",
                "package.manual_pause",
                "package.tipIdentifier",
                "package.tableIdentifier",
                "subscription.id AS subscriptionId",
                "subscription.status AS subscriptionStatus"
            )
            ->leftJoin("subscription", "subscription.packageId", "package.id")
            ->join("auto_unit_daily_schedule", "auto_unit_daily_schedule.siteId", "package.siteId")
            ->whereRaw("subscription.id = (SELECT MAX(subscription.id) FROM subscription WHERE subscription.packageId = package.id)")
            ->where("auto_unit_daily_schedule.systemDate", "=", $today)
            ->groupBy("package.id")
            ->get();

        foreach ($packages as $package) {
            if (
                $package->subscriptionId == null || 
                ($package->paused_autounit && 
                !$package->manual_pause && 
                $package->subscriptionStatus == "archived")
            ) {
                $package->paused_autounit = 0;
                $package->save();

                $subscriptionAlert = new SubscriptionAlert();
                $subscriptionAlert->store($package); 
                
            } else if ($package->paused_autounit) {
                var_dump($package->id . " --- " . $package->subscriptionStatus . " paused: " . $package->paused_autounit . " manual pase: " . $package->manual_pause);
                \App\Models\AutoUnit\DailySchedule::where("tipIdentifier", "=", $package->tipIdentifier)
                    ->where("tableIdentifier", "=", $package->tableIdentifier)
                    ->where("siteId", "=", $package->siteId)
                    ->where("systemDate", "=", $today)
                    ->delete();
            }
        }
    }
}