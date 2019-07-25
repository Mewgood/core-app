<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Site;
use Illuminate\Support\Facades\DB;

class Distribution extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /*
     * @string $date format: Y-m-d || 0 || null
     * get all distributed events for specific date.
     * @return array()
     */
    public function index($date = null)
    {
        $data = [];

        // default set current date GMT
        if ($date === null || $date == 0)
            $date = gmdate('Y-m-d');

        foreach (\App\Site::all() as $site) {
            // set siteName
            $data[$site->id]['name'] = $site->name;
            $data[$site->id]['siteId'] = $site->id;

            // get associated packages frm site_package
            $assocPacks = \App\SitePackage::select('packageId')->where('siteId', $site->id)->get()->toArray();
            foreach ($assocPacks as $assocPack) {
                // get package
                $package = \App\Package::find($assocPack['packageId']);

                // get events for package
                $distributedEvents = \App\Distribution::where('packageId', $package->id)->where('systemdate', $date)->get();

                // add status to distributed events
                foreach ($distributedEvents as $distributedEvent)
                    $distributedEvent->status;

                $data[$site->id]['packages'][$assocPack['packageId']]['id'] = $package->id;
                $data[$site->id]['packages'][$assocPack['packageId']]['name'] = $package->name;
                $data[$site->id]['packages'][$assocPack['packageId']]['tipsPerDay'] = $package->tipsPerDay;
                $data[$site->id]['packages'][$assocPack['packageId']]['eventsNumber'] = count($distributedEvents);
                $data[$site->id]['packages'][$assocPack['packageId']]['events'] = $distributedEvents;

                $customerNotEnoughTips = 0;

                // check for customer with not enough tips only for current date
                if ($date == gmdate('Y-m-d')) {
                    $subscriptionInstance = new \App\Http\Controllers\Admin\Subscription();
                    $subscriptionIdsNotEnoughTips = $subscriptionInstance->getSubscriptionsIdsWithNotEnoughTips($package->id);

                    foreach ($subscriptionIdsNotEnoughTips as $subscriptionId) {
                        $subscription = \App\Subscription::find($subscriptionId);

                        // get total availlable tips
                        $totalTips = $subscription->tipsLeft - $subscription->tipsBlocked;
                        $todayTipsNumber = \App\Distribution::where('packageId', $subscription->packageId)
                            ->where('systemDate', gmdate('Y-m-d'))->count();

                        // get number of restricted tips
                        $restrictedTips = \App\SubscriptionRestrictedTip::where('subscriptionId', $subscription->id)
                            ->where('systemDate', gmdate('Y-m-d'))
                            ->count();

                        // increase number of customers who not have enought tips
                        if (($todayTipsNumber - $restrictedTips) > $totalTips)
                            $customerNotEnoughTips++;
                    }
                }

                $data[$site->id]['packages'][$assocPack['packageId']]['customerNotEnoughTips'] = $customerNotEnoughTips;
            }
        }

        return $data;
    }

	/*
     * @string $date format: Y-m-d || 0 || null
     * get all distributed events for specific date.
     * @return array()
     */
    public function getDistributionsGrouped($date = null , Request $r)
    {
        $mappedData = [];
        // sort the results 
        $real_user_sort = strtolower($r->input('real_user_sort'));
        $vip_user_sort = strtolower($r->input('vip_user_sort'));
        $emails_sort =  strtolower($r->input('emails_sort'));
        $no_tip_vip =  $r->input('no_tip_vip');

        // default set current date GMT
        if ($date === null || $date == 0)
            $date = gmdate('Y-m-d');

        $data = Site::getSitesDistributions($date, $real_user_sort, $vip_user_sort, $emails_sort, $no_tip_vip);

        foreach ($data as $item) {
            $this->mapSiteDistributions($item, $mappedData);
        }
        
        return $mappedData;
    }
    
    private function mapSiteDistributions($item, &$mappedData) 
    {
        $distributionIds = "";
        for ($i = 0; $i < $item->totalEvents; $i++) {
            $distributionIds .= $item->distributionId + $i . ", ";
        }
        $distributionIds = rtrim($distributionIds, ", ");

        $item->distributionIds = $distributionIds;
        $mappedData[$item->siteId]["tips"][$item->tipIdentifier]["siteName"] = $item->siteName;
        $mappedData[$item->siteId]["tips"][$item->tipIdentifier]["rowCount"] = $item->totalEvents ? $item->totalEvents : 1;
        $mappedData[$item->siteId]["tips"][$item->tipIdentifier]["isVip"] = $item->isVip;
        $mappedData[$item->siteId]["tips"][$item->tipIdentifier]["events"][] = $item;
        if (
            $item->totalEvents < count($mappedData[$item->siteId]["tips"][$item->tipIdentifier]["events"]) && 
            count($mappedData[$item->siteId]["tips"][$item->tipIdentifier]["events"]) > 1
        ) {
            array_pop($mappedData[$item->siteId]["tips"][$item->tipIdentifier]["events"]);
        }
    }

    public function get() {}

    // @param $timeStart format h:mm || hh:mm
    // @param $timeEndformat h:mm || hh:mm
    // will create date schedule, when email will be send.
    // @return array()
    public function createEmailSchedule(Request $r)
    {
        $timeStart = $r->input('timeStart');
        $timeEnd = $r->input('timeEnd');

        if (!$timeStart || ! $timeEnd)
            return [
                'type' => 'error',
                'message' => 'Please choose time to start and end.',
            ];

        $hStart = explode(':', $timeStart)[0];
        $hStart = strlen($hStart) == 1 ? '0' . $hStart : $hStart;
        $mStart = explode(':', $timeStart)[1];

        $hEnd = explode(':', $timeEnd)[0];
        $hEnd = strlen($hEnd) == 1 ? '0' . $hEnd : $hEnd;
        $mEnd = explode(':', $timeEnd)[1];

        $timeStart = strtotime(gmdate('Y-m-d') . ' ' . $hStart . ':' . $mStart . ':00');
        $timeEnd = strtotime(gmdate('Y-m-d') . ' ' . $hEnd . ':' . $mEnd . ':00');

        if ($timeStart  < (time() + (10 * 60)))
            return [
                'type' => 'error',
                'message' => "Start must be greather with 10 min than current GMT time: \n" . gmdate('Y-m-d H:i:s'),
            ];

        $events = \App\Distribution::where('isEmailSend', '0')
            ->where('systemDate', gmdate('Y-m-d'))
            ->where('eventDate', '>', gmdate('Y-m-d H:i:s', strtotime('+10min')))
            ->whereNull('mailingDate')
            ->get();

        $sites = [];  // [siteId=> [common sites ids], ....]
        foreach($events as $event) {
            $eventDate = strtotime($event->eventDate);
            /* $eventDate = $event->eventDate; */

            if (!isset($sites[$event->siteId])) {
                $sites[$event->siteId] = [
                    'time' => $eventDate,
                    'commonUsersWith' => [],
                ];
            }

            if ($eventDate < $sites[$event->siteId]['time']) {
                $sites[$event->siteId]['time'] = $eventDate;
            }
        }

        foreach ($sites as $k => $site) {
            if ($site['time'] < $timeEnd) {
                unset($sites[$k]);

                // return if you have events that start before schedule end
                return [
                    'type' => 'error',
                    'message' => 'You can not have events that start before schedule end!',
                ];
            }
        }

        foreach ($sites as $k => $v) {
            $customers = \App\Subscription::select('customerId')
                ->where('siteId', $k)
                ->where('status', 'active')
                ->get();

            foreach ($customers as $customer) {
                $custom = \App\Customer::find($customer->customerId);
                $customerEntities = \App\Customer::where('email', $custom->email)
                    ->get();

                $customerIds = [];
                foreach ($customerEntities as $v)
                    $customerIds[] = $v->id;

                $commonSites = \App\Subscription::select('siteId')
                    ->whereIn('customerId', $customerIds)
                    ->where('status', 'active')
                    ->get();

                foreach ($commonSites as $commonSite) {
                    if ($commonSite->siteId == $k)
                        continue;

                    if (! in_array( $commonSite->siteId, $sites[$k]))
                        $sites[$k]['commonUsersWith'][] = $commonSite->siteId;
                }
            }
        }

        $emailScheduler = new \App\Src\Distribution\EmailSchedule($sites, $timeStart, $timeEnd);
        $emailScheduler->createSchedule();
        $scheduled = $emailScheduler->getEventsOrdered();

        $msg = '';
        if ($emailScheduler->error) {
            foreach ($emailScheduler->error as $e)
                $msg .= "\r\n" . $e;

            return [
                'type' => 'error',
                'message' => $msg,
            ];
        }

        $scheduledNumber = 0;
        foreach ($events as $event) {
            if (isset($scheduled['data'][$event->siteId])) {
                $event->mailingDate = $scheduled['data'][$event->siteId]['schedule'];
                $event->save();
                $scheduledNumber++;
            }
        }

        return [
            'type' => 'success',
            'message' => 'Email Scheduler was created with success for: ' . $scheduledNumber .' events!',
        ];
    }

    // will delete date scheduled for events that not sended by email yet.
    // This wil worl only for today events
    // @return array()
    public function deleteEmailSchedule()
    {
        $events = \App\Distribution::where('isEmailSend', '0')
            ->where('systemDate', gmdate('Y-m-d'))
            ->whereNotNull('mailingDate')
            ->where('mailingDate', '>', date('Y-m-d H:i:s', time() + 60))
            ->get();

        foreach ($events as $e) {
            $e->mailingDate = null;
            $e->save();
        }

        return [
            'type' => 'success',
            'message' => 'Was canceled schedule for: ' . count($events) .' events!',
        ];
    }

    public function setTimeEmailSchedule(Request $r)
    {
        $ids = $r->input('ids');
        $time = $r->input('time');

        if (!$ids)
            return [
                'type' => 'error',
                'message' => 'No events selected!',
            ];

        $hTime = explode(':', $time)[0];
        $hTime = strlen($hTime) == 1 ? '0' . $hTime : $hTime;
        $mTime = explode(':', $time)[1];
        $mailingDate = gmdate('Y-m-d') . ' ' . $hTime . ':' . $mTime . ':00';

        if ($mailingDate < gmdate('Y-m-d H:i:s', strtotime('+2min')))
            return [
                'type' => 'error',
                'message' => 'Datethat you selected must be greather with 2 minutes then current GMT date!',
            ];

        $alreadySend = 0;
        $notAvailable = 0;
        $greatherThanEventDate = 0;
        $modified = 0;
        $events = \App\Distribution::whereIn('id', $ids)->get();
        foreach ($events as $e) {
            if ($e->isEmailSend) {
                $alreadySend++;
                continue;
            }
            if ($e->eventDate < gmdate('Y-m-d H:i:s', strtotime('+2min'))) {
                $notAvailable++;
                continue;
            }
            if ($e->eventDate < $mailingDate) {
                $greatherThanEventDate++;
                    continue;
            }

            $e->mailingDate = $mailingDate;
            $e->save();
            $modified++;
        }

        $message = '';
        if($alreadySend)
            $message .= "$alreadySend: already send by email. \r\n";
        if($notAvailable)
            $message .= "$notAvailable: start in less then 2 minutes.\r\n";
        if($greatherThanEventDate)
            $message .= "$greatherThanEventDate: new mailing date is greather than event date.\r\n";
        if($modified)
            $message .= "$modified: was modified.\r\n";

        return [
            'type' => 'success',
            'message' => $message,
        ];
    }

    /*
     * @param string $eventId
     * @param array  $packagesIds
     * delete distributions of event - package (if packageId is not in $packagesIds)
     *    - Not Delete events hwo was already published
     * create new associations event - packages
     */
    public function storeAndDelete(Request $request) {
        // check if association still exist
        if (\App\Association::find($request->input('eventId')) === null)
            return response()->json([
                "type" => "error",
                "message" => "association id: " . $request->input('eventId') . "not exist anymore!"
            ]);

        // get association as object
        $association = \App\Association::where('id', $request->input('eventId'))->first();

        //transform in array
        $association = json_decode(json_encode($association), true);

        unset($association['created_at']);
        unset($association['updated_at']);

        $packagesIds = $request->input('packagesIds') ? $request->input('packagesIds') : [];

        // create array with existing packageId
        // also delete unwanted distribution
        $deleted = 0;
        $distributionExists = [];
        $message = '';
        $distributedEvents = \App\Distribution::where('associationId', $association['id'])->get();

        // group packages by site and tipIdentifier
        $group = [];
        foreach ($distributedEvents as $item) {
            if ($item->isPublish || $item->isEmailSend)
                $group[$item->siteId][$item->tipIdentifier] = true;
        }

        foreach ($distributedEvents as $item) {
            // delete distribution
            if (!in_array($item->packageId, $packagesIds)) {

                if (isset($group[$item->siteId][$item->tipIdentifier])) {
                    $message .= "Can not delete association with package $item->packageId, was already published or email send. Or nother package with same tip publish this event.\r\n";
                    continue;
                }
                $item->delete();
                $deleted++;
            }
            $distributionExists[] = $item->packageId;
        }

        if ($message !== '')
            return [
                "type" => "error",
                "message" => $message
            ];

        // id from association table became associationId
        $association['associationId'] = $association['id'];
        unset($association['id']);

        $inserted = 0;
        $alreadyExists = 0;
        $message = '';;
        foreach ($packagesIds as $id) {

            // do not insert if already exists
            if (in_array($id, $distributionExists)) {
                $alreadyExists++;
                continue;
            }

            // get package
            $package = \App\Package::find($id);
            if (!$package) {
                $message = "Could not find package with id: $id, maybe was deleted \r\n";
                continue;
            }

            // get siteId by package
            $packageSite = \App\SitePackage::where('packageId', $id)->first();
            if (!$packageSite) {
                $message = "Could not associate event with package id: $id, this package must be associated with a site\r\n";
                continue;
            }

            if (!$association['isNoTip']) {
                // get site prediction name
                $sitePrediction = \App\SitePrediction::where([
                    ['siteId', '=', $packageSite->siteId],
                    ['predictionIdentifier', '=', $association['predictionId']]
                ])->first();

                // set predictionName
                $association['predictionName'] = $sitePrediction->name;
            }

            // set siteId
            $association['siteId'] = $packageSite->siteId;

            // set tableIdentifier
            $association['tableIdentifier'] = $package->tableIdentifier;

            // set tipIdentifier
            $association['tipIdentifier'] = $package->tipIdentifier;

            // set packageId
            $association['packageId'] = $id;
			
			// get the aliases - added by GDM
			$homeTeamAlias = \App\Models\Team\Alias::where('teamId', $association['homeTeamId'] )->first();
			if( $homeTeamAlias && $homeTeamAlias->alias && $homeTeamAlias->alias != '' ) {
				$association['homeTeam'] = $homeTeamAlias->alias;
			}		
			$awayTeamAlias = \App\Models\Team\Alias::where('teamId', $association['awayTeamId'] )->first();
			if( $awayTeamAlias && $awayTeamAlias->alias && $awayTeamAlias->alias != '' ) {
				$association['awayTeam'] = $awayTeamAlias->alias;
			}		
			$leagueAlias = \App\Models\League\Alias::where('leagueId', $association['leagueId'] )->first();
			if( $leagueAlias && $leagueAlias->alias && $leagueAlias->alias != '' ) {
				$association['league'] = $leagueAlias->alias;
			}
			
			$countryAlias = \App\Models\Country\Alias::where('countryCode', $association['countryCode'] )->first();
			if( $countryAlias && $countryAlias->alias && $countryAlias->alias != '' ) {
				$association['country'] = $countryAlias->alias;
			}
			

            \App\Distribution::create($association);
            $inserted++;
        }

        if($inserted)
            $message .= "$inserted: new distribution added \r\n";
        if($deleted)
            $message .= "$deleted: distribution was deleted \r\n";
        if($alreadyExists)
            $message .= "$alreadyExists: distribution already exists \r\n";

        return [
            "type" => "success",
            "message" => $message
        ];

    }

    public function update() {}

    // @param array $ids
    // @param string|null|false $template
    // This will add events to subscriptions, also will move events to email schedule.
    // return array();
    public function associateEventsWithSubscription($ids, $template = false)
    {
        // validate events selection
        $validate = new \App\Http\Controllers\Admin\Email\ValidateGroup($ids);
        if ($validate->error)
            return [
                'type' => 'error',
                'message' => $validate->message,
            ];

        $subscriptions = \App\Subscription::where('packageId', $validate->packageId)
            ->where('status', 'active')->get();

        if (!count($subscriptions))
            return [
                'type'    => 'success',
                'message' => 'No active subscriptions for packageId: ' . $validate->packageId . "\r\n",
            ];

        // update tips distribution and set mailingDate and is EmailSend
        foreach ($ids as $id) {
            $distribution = \App\Distribution::find($id);

            if (! $distribution->mailingDate)
                $distribution->mailingDate = gmdate('Y-m-d H:i:s');

            $distribution->isEmailSend = '1';
            $distribution->update();
        }

        // get package
        $package = \App\Package::find($validate->packageId);

        // get site by packageId;
        $site = \App\Site::find($package->siteId);

        // get events from database.
        $events = \App\Distribution::whereIn('id', $ids)->get()->toArray();

        // set eventDate  according to date format of site
        foreach ($events as $k => $event) {
            $events[$k]['eventDate'] = date($site->dateFormat, strtotime($event['eventDate']));
        }

        $message = "Start sending emails to: \r\n";

        // when use send will not edit template, will not have custom template
        // here we must remove section
        if (! $template) {
            $replaceSection = new \App\Http\Controllers\Admin\Email\RemoveSection($package->template, $validate->isNoTip);
            $template = $replaceSection->template;
        }

        foreach ($subscriptions as $s) {

            // remove restricted events
            $subscriptionEvents = $events;
            foreach ($subscriptionEvents as $k => $e) {
                $isRestricted = \App\SubscriptionRestrictedTip::where('subscriptionId', $s->id)
                    ->where('distributionId', $e['id'])->count();

                if ($isRestricted)
                    unset($subscriptionEvents[$k]);
            }

            // if for a subscription there is no event continue.
            // let say all tips are restricted
            if (! $subscriptionEvents)
                continue;

            // if subscription type = tips
            // will move number of sbscription events from tipsLeft to tipsBlocked
            // Do not do this for noTip
            if ($s->type === 'tips' && !$validate->isNoTip) {
                $eventsNumber = count($subscriptionEvents);
                $s->tipsBlocked += $eventsNumber;
                $s->tipsLeft -= $eventsNumber;
                $s->update();

                // archive subscription if it don't have tips
                $subscriptionInstance = new \App\Http\Controllers\Admin\Subscription();
                $subscriptionInstance->manageTipsSubscriptionStatus($s);
            }

            $customer = \App\Customer::find($s->customerId);
            $message .= $customer->name . ' - ' .$customer->email . "\r\n";

            // insert all events in subscription_tip_history
            foreach ($subscriptionEvents as $event) {
				// get the aliases - added by GDM
				$homeTeamAlias = \App\Models\Team\Alias::where('teamId', $event['homeTeamId'] )->first();
				if( $homeTeamAlias && $homeTeamAlias->alias && $homeTeamAlias->alias != '' ) {
					$event['homeTeam'] = $homeTeamAlias->alias;
				}		
				$awayTeamAlias = \App\Models\Team\Alias::where('teamId', $event['awayTeamId'] )->first();
				if( $awayTeamAlias && $awayTeamAlias->alias && $awayTeamAlias->alias != '' ) {
					$event['awayTeam'] = $awayTeamAlias->alias;
				}		
				$leagueAlias = \App\Models\League\Alias::where('leagueId', $event['leagueId'] )->first();
				if( $leagueAlias && $leagueAlias->alias && $leagueAlias->alias != '' ) {
					$event['league'] = $leagueAlias->alias;
				}
				
				$countryAlias = \App\Models\Country\Alias::where('countryCode', $event['countryCode'] )->first();
				if( $countryAlias && $countryAlias->alias && $countryAlias->alias != '' ) {
					$event['country'] = $countryAlias->alias;
				}
				
				
				
                // here will use eventId for event table.
                \App\SubscriptionTipHistory::create([
                    'customerId' => $customer->id,
                    'subscriptionId' => $s->id,
                    'eventId' => $event['eventId'],
                    'siteId'  => $s->siteId,
                    'isCustom' => $s->isCustom,
                    'type' => $s->type,
                    'isNoTip' => $event['isNoTip'],
                    'isVip' => $event['isVip'],
                    'country' => $event['country'],
                    'countryCode' => $event['countryCode'],
                    'league' => $event['league'],
                    'leagueId' => $event['leagueId'],
                    'homeTeam' => $event['homeTeam'],
                    'homeTeamId' => $event['homeTeamId'],
                    'awayTeam' => $event['awayTeam'],
                    'awayTeamId' => $event['awayTeamId'],
                    'predictionId' => $event['predictionId'],
                    'predictionName' => $event['predictionName'],
                    'eventDate' => gmdate( $site->dateFormat , strtotime($event['eventDate']) ),
                    'systemDate' => $event['systemDate'],
                ]);
            }

            // replace tips in template
            $replaceTips = new \App\Http\Controllers\Admin\Email\ReplaceTipsInTemplate($template, $subscriptionEvents, $validate->isNoTip);

            // replace customer information in template
            $replaceCustomerInfoTemplate = new \App\Http\Controllers\Admin\Email\ReplaceCustomerInfoInTemplate(
                $replaceTips->template,
                $customer
            );

            // store all data to send email
            $args = [
                'provider'        => 'site',
                'sender'          => $site->id,
                'type'            => 'subscriptionEmail',
                'identifierName'  => 'subscriptionId',
                'identifierValue' => $s->id,
                'from'            => $site->email,
                'fromName'        => $package->fromName,
                'to'              => $customer->activeEmail,
                'toName'          => $customer->name ? $customer->name : $customer->activeEmail,
                'subject'         => str_replace('{{date}}', gmdate($site->dateFormat, time()), $package->subject),
                'body'            => $replaceCustomerInfoTemplate->template,
                'status'          => 'waiting',
            ];

            // insert in email_schedule
            \App\EmailSchedule::create($args);
        }

        // send also email to site email for confimation tips are sended.
        $replaceTipsInTemplateInstance = new \App\Http\Controllers\Admin\Email\ReplaceTipsInTemplate($template, $events, $validate->isNoTip);
        $template = $replaceTipsInTemplateInstance->template;

        \App\EmailSchedule::create([
            'provider'        => 'packageDailyTips',
            'sender'          => $site->id,
            'type'            => 'dailyTipsCheck',
            'identifierName'  => 'packageId',
            'identifierValue' => $package->id,
            'from'            => getenv('EMAIL_USER'),
            'fromName'        => $package->fromName,
            'to'              => $site->email,
            'toName'          => $site->name,
            'subject'         => str_replace('{{date}}', gmdate($site->dateFormat, time()), $package->subject),
            'body'            => $template,
            'status'          => 'waiting',
        ]);

        return [
            'type'    => 'success',
            'message' => $message,
        ];
    }

    /*
     * @param array $ids
     * delete distributed events
     *   - Not Delete events already sended in archives
     */
    public function destroy(Request $r) {
        $ids = $r->input('ids');

        if (!$ids)
            return [
                "type" => "error",
                "message" => "No events provided!",
            ];

        $notFound = 0;
        $canNotDelete = 0;
        $deleted = 0;
        $forceDestroy = false;
        foreach ($ids as $id) {

            $distribution = \App\Distribution::find($id);

            if (!$distribution) {
                $notFound++;
                continue;
            }

            if ($distribution->isPublish) {
                $canNotDelete++;
                $forceDestroy = true;
                continue;
            }

            if ($distribution->isEmailSend) {
                $canNotDelete++;
                $forceDestroy = true;
                continue;
            }

            $distribution->delete();
            $deleted++;
        }

        $message = '';
        if ($notFound)
            $message .= "$notFound events not founded, maybe was deleted.\r\n";
        if ($canNotDelete) {
            $message .= "Are you sure you want to force delete those: \r\n";
            $message .= "$canNotDelete events must be forced to delete becouse they are already sended by email or publish to archives..\r\n";
        }
        if ($deleted)
            $message .= "$deleted events was successful deleted.\r\n";

        return [
            "type" => "success",
            "message" => $message,
            "forceDestroy" => $forceDestroy,
        ];
    }

    /*
     * @param array $ids
     * delete distributed events
     *   - Not Delete events already sended in archives
     */
    public function forceDestroy(Request $r) {
        $ids = $r->input('ids');
        $type = $r->input('type') ? $r->input('type') : "distribution";

        if (!$ids)
            return [
                "type" => "error",
                "message" => "No events provided!",
            ];

        $deleted = 0;
        $message = '';
        
        if ($type == "distribution") {
            foreach ($ids as $id) {
                $distribution = \App\Distribution::find($id);

                if (!$distribution) {
                    continue;
                }

                \App\ArchiveHome::where('eventId', $distribution->eventId)
                    ->where('siteId', $distribution->siteId)
                    ->delete();

                \App\ArchiveBig::where('eventId', $distribution->eventId)
                    ->where('siteId', $distribution->siteId)
                    ->delete();

                \App\SubscriptionRestrictedTip::where('distributionId', $distribution->id)
                    ->where('systemDate', $distribution->systemDate)
                    ->delete();

                \App\SubscriptionTipHistory::where('eventId', $distribution->eventId)
                    ->where('siteId', $distribution->siteId)
                    ->delete();
                
                

                \App\Models\AutoUnit\DailySchedule::join("match", "match.primaryId", "auto_unit_daily_schedule.match_id")
                    ->join("event", "event.matchId", "match.id")
                    ->join("distribution", "distribution.eventId", "event.id")
                    ->where("distribution.id", "=", $distribution->id)
                    ->delete();
                    
                if (! \App\ArchivePublishStatus::where('siteId' , $distribution['siteId'])->where('type' , 'archiveBig')->count())
                    \App\ArchivePublishStatus::create([
                        'siteId' => $distribution['siteId'],
                        'type'   => 'archiveBig'
                    ]);

                if (! \App\ArchivePublishStatus::where('siteId' , $distribution['siteId'])->where('type' , 'archiveHome')->count())
                    \App\ArchivePublishStatus::create([
                        'siteId' => $distribution['siteId'],
                        'type'   => 'archiveHome'
                    ]);

                $distribution->delete();
                $distributions = \App\Distribution::where("eventId", "=", $distribution->eventId)
                    ->get();

                // delete association if no event is associated with a site
                if (!count($distributions)) {
                    \App\Association::where("association.eventId", "=", $distribution->eventId)
                        ->where("provider", "=", "autounit")
                        ->delete();
                }
                $deleted++;
            }
        } else {
            \App\Models\AutoUnit\DailySchedule::where("auto_unit_daily_schedule.id", "=", $ids)->delete();
            $deleted++;
        }

        $message .= "$deleted events was successful forced to delete.\r\n";

        return [
            "type" => "success",
            "message" => $message,
        ];
    }
	
	
	
	// @param array $ids
    // @param string|null|false $template
    // This will add events to subscriptions, also will move events to email schedule.
	// Created by GDM for the new Distributions page 
    // return array();
    public function associateEventsWithSubscriptionUpdated($ids, $template = false)
    {
        // validate events selection
        $validate = new \App\Http\Controllers\Admin\Email\ValidateGroupUpdated($ids);
        if ($validate->error)
            return [
                'type' => 'error',
                'message' => $validate->message,
            ];

        $subscriptions = \App\Subscription::where('packageId', $validate->packageId)
            ->where('status', 'active')->get();

        if (!count($subscriptions))
            return [
                'type'    => 'success',
                'message' => 'No active subscriptions for packageId: ' . $validate->packageId . "\r\n",
            ];

        // update tips distribution and set mailingDate and is EmailSend
        foreach ($ids as $id) {
            $distribution = \App\Distribution::find($id);

            if (! $distribution->mailingDate)
                $distribution->mailingDate = gmdate('Y-m-d H:i:s');

            $distribution->isEmailSend = '1';
            $distribution->update();
        }

        // get package
        $package = \App\Package::find($validate->packageId);

        // get site by packageId;
        $site = \App\Site::find($package->siteId);

        // get events from database.
        $events = \App\Distribution::whereIn('id', $ids)->get()->toArray();

        // set eventDate  according to date format of site
        foreach ($events as $k => $event) {
            $events[$k]['eventDate'] = date($site->dateFormat, strtotime($event['eventDate']));
        }

        $message = "Start sending emails to: \r\n";

        // when use send will not edit template, will not have custom template
        // here we must remove section
        if (! $template) {
            $replaceSection = new \App\Http\Controllers\Admin\Email\RemoveSection($package->template, $validate->isNoTip);
            $template = $replaceSection->template;
        }

        foreach ($subscriptions as $s) {

            // remove restricted events
            $subscriptionEvents = $events;
            foreach ($subscriptionEvents as $k => $e) {
                $isRestricted = \App\SubscriptionRestrictedTip::where('subscriptionId', $s->id)
                    ->where('distributionId', $e['id'])->count();

                if ($isRestricted)
                    unset($subscriptionEvents[$k]);
            }

            // if for a subscription there is no event continue.
            // let say all tips are restricted
            if (! $subscriptionEvents)
                continue;

            // if subscription type = tips
            // will move number of sbscription events from tipsLeft to tipsBlocked
            // Do not do this for noTip
            if ($s->type === 'tips' && !$validate->isNoTip) {
                $eventsNumber = count($subscriptionEvents);
                $s->tipsBlocked += $eventsNumber;
                $s->tipsLeft -= $eventsNumber;
                $s->update();

                // archive subscription if it don't have tips
                $subscriptionInstance = new \App\Http\Controllers\Admin\Subscription();
                $subscriptionInstance->manageTipsSubscriptionStatus($s);
            }

            $customer = \App\Customer::find($s->customerId);
            $message .= $customer->name . ' - ' .$customer->email . "\r\n";

            // insert all events in subscription_tip_history
            foreach ($subscriptionEvents as $event) {
				// get the aliases - added by GDM
				$homeTeamAlias = \App\Models\Team\Alias::where('teamId', $event['homeTeamId'] )->first();
				if( $homeTeamAlias && $homeTeamAlias->alias && $homeTeamAlias->alias != '' ) {
					$event['homeTeam'] = $homeTeamAlias->alias;
				}		
				$awayTeamAlias = \App\Models\Team\Alias::where('teamId', $event['awayTeamId'] )->first();
				if( $awayTeamAlias && $awayTeamAlias->alias && $awayTeamAlias->alias != '' ) {
					$event['awayTeam'] = $awayTeamAlias->alias;
				}		
				$leagueAlias = \App\Models\League\Alias::where('leagueId', $event['leagueId'] )->first();
				if( $leagueAlias && $leagueAlias->alias && $leagueAlias->alias != '' ) {
					$event['league'] = $leagueAlias->alias;
				}
				
				$countryAlias = \App\Models\Country\Alias::where('countryCode', $event['countryCode'] )->first();
				if( $countryAlias && $countryAlias->alias && $countryAlias->alias != '' ) {
					$event['country'] = $countryAlias->alias;
				}
				
				
                // here will use eventId for event table.
                \App\SubscriptionTipHistory::create([
                    'customerId' => $customer->id,
                    'subscriptionId' => $s->id,
                    'eventId' => $event['eventId'],
                    'siteId'  => $s->siteId,
                    'isCustom' => $s->isCustom,
                    'type' => $s->type,
                    'isNoTip' => $event['isNoTip'],
                    'isVip' => $event['isVip'],
                    'country' => $event['country'],
                    'countryCode' => $event['countryCode'],
                    'league' => $event['league'],
                    'leagueId' => $event['leagueId'],
                    'homeTeam' => $event['homeTeam'],
                    'homeTeamId' => $event['homeTeamId'],
                    'awayTeam' => $event['awayTeam'],
                    'awayTeamId' => $event['awayTeamId'],
                    'predictionId' => $event['predictionId'],
                    'predictionName' => $event['predictionName'],
                    'eventDate' => gmdate( $site->dateFormat , strtotime($event['eventDate']) ),
                    'systemDate' => $event['systemDate'],
                ]);
            }

            // replace tips in template
            $replaceTips = new \App\Http\Controllers\Admin\Email\ReplaceTipsInTemplate($template, $subscriptionEvents, $validate->isNoTip);

            // replace customer information in template
            $replaceCustomerInfoTemplate = new \App\Http\Controllers\Admin\Email\ReplaceCustomerInfoInTemplate(
                $replaceTips->template,
                $customer
            );

            // store all data to send email
            $args = [
                'provider'        => 'site',
                'sender'          => $site->id,
                'type'            => 'subscriptionEmail',
                'identifierName'  => 'subscriptionId',
                'identifierValue' => $s->id,
                'from'            => $site->email,
                'fromName'        => $package->fromName,
                'to'              => $customer->activeEmail,
                'toName'          => $customer->name ? $customer->name : $customer->activeEmail,
                'subject'         => str_replace('{{date}}', gmdate($site->dateFormat, time()), $package->subject),
                'body'            => $replaceCustomerInfoTemplate->template,
                'status'          => 'waiting',
            ];

            // insert in email_schedule
            \App\EmailSchedule::create($args);
        }

        // send also email to site email for confimation tips are sended.
        $replaceTipsInTemplateInstance = new \App\Http\Controllers\Admin\Email\ReplaceTipsInTemplate($template, $events, $validate->isNoTip);
        $template = $replaceTipsInTemplateInstance->template;

        \App\EmailSchedule::create([
            'provider'        => 'packageDailyTips',
            'sender'          => $site->id,
            'type'            => 'dailyTipsCheck',
            'identifierName'  => 'packageId',
            'identifierValue' => $package->id,
            'from'            => getenv('EMAIL_USER'),
            'fromName'        => $package->fromName,
            'to'              => $site->email,
            'toName'          => $site->name,
            'subject'         => str_replace('{{date}}', gmdate($site->dateFormat, time()), $package->subject),
            'body'            => $template,
            'status'          => 'waiting',
        ]);

        return [
            'type'    => 'success',
            'message' => $message,
        ];
    }
	
}
