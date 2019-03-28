<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

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
        $data = [];
        $response_data = [];

        // default set current date GMT
        if ($date === null || $date == 0)
            $date = gmdate('Y-m-d');

        foreach (\App\Site::all() as $site) {
						
            // set siteName
            $data[$site->id]['siteName'] = $site->name;
            $data[$site->id]['siteId'] = $site->id;
			
			$data[$site->id]['package_types'] = [];
			
			// $data[$site->id]['packages'] = [];

            // get associated packages frm site_package
            $assocPacks = \App\SitePackage::select('packageId')->where('siteId', $site->id)->get()->toArray();
            foreach ($assocPacks as $assocPack) {
                // get package
                $package = \App\Package::find($assocPack['packageId']);

                // get events for package
                $distributedEvents = \App\Distribution::select("distribution.*", "auto_unit_daily_schedule.is_from_admin_pool")
                    ->leftJoin("event", "event.id", "distribution.eventId")
                    ->leftJoin("match", "match.id", "event.matchId")
                    ->leftJoin("auto_unit_daily_schedule", function($query) {
                        $query->on("auto_unit_daily_schedule.match_id", "match.primaryId");
                        $query->on("auto_unit_daily_schedule.siteId", "distribution.siteId");
                    })
                    ->where('distribution.packageId', $package->id)
                    ->where('distribution.systemdate', $date)
                    ->get();

                // add status to distributed events
                foreach ($distributedEvents as $distributedEvent)
                    $distributedEvent->status;
				
				// grup the packages by package type
				if( !isset( $data[$site->id]['packageTypes'][$package->tipIdentifier] ) && !isset( $data[$site->id]['packageTypes'][$package->tipIdentifier]['packages'] ) ) {
					$data[$site->id]['packageTypes'][$package->tipIdentifier]['packages'] = [];
				}
				
				// prepare the distribution's package data 
				$package_data = [];
				$package_data['id'] = $package->id;
				$package_data['isVip'] = $package->isVip;
                $package_data['name'] = $package->name;
                $package_data['tipsPerDay'] = $package->tipsPerDay;
                $package_data['eventsNumber'] = count($distributedEvents);
                $package_data['events'] = $distributedEvents;
                $package_data['customerNotEnoughTips'] = 0;
				
				$data[$site->id]['packageTypes'][$package->tipIdentifier]['packages'][$assocPack['packageId']] = $package_data;
				
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

                $data[$site->id]['packageTypes'][$package->tipIdentifier]['packages'][$assocPack['packageId']]['customerNotEnoughTips'] = $customerNotEnoughTips;
				
				
            }
			
        }
		
		// loop through the 'original' distributions and re-arrage and prepare the final distribution list.
		foreach( $data as $site_data ) {
			$row_data_temp = [];
			$row_data_temp['siteName'] = $site_data['siteName'];
			$row_data_temp['siteId'] = $site_data['siteId'];
			$row_data_temp['isTotalEmailSend'] = 0;
			$row_data_temp['totalEmails'] = 0;
			$row_data_temp['totalSentEmails'] = 0;
			$row_data_temp['isTotalPublish'] = 0;
			
			// Site without packages
			if( !isset($site_data['packageTypes']) ) {
				$row_data_temp['type'] = '';
				$row_data_temp['ruNu'] = '';
				$row_data_temp['isVip'] = '';
				$row_data_temp['packages'] = [];
				$row_data_temp['distributionsIds'] = [];
				$row_data_temp['distributionIdsString'] = '';
				$row_data_temp['events'] = [];
				$row_data_temp['eventsCount'] = 0;
				$response_data[] = $row_data_temp;
				continue;
			}
			
			foreach($site_data['packageTypes'] as $identifier => $tipIdentifierPackages ) {
				$row_data_temp['type'] = $identifier;
				$row_data_temp['packages'] = [];
				$row_data_temp['distributionsIds'] = [];
				$row_data_temp['distributionIdsString'] = '';
				$row_data_temp['events'] = [];
				$row_data_temp['eventsCount'] = 0;
				$packageType_events = [];
				$eventIds = [];
				
				
				foreach($tipIdentifierPackages['packages'] as $package ) {
					
					// get the RU/NU property
					$p_userType = '';
					$userType = \App\PackageSection::where('packageId', $package['id'])
							->where('systemDate', $date)
							->first();					
					if( $userType ) {
						$p_userType = strtoupper($userType->section);
					}
                    //dd($userType);
					$row_data_temp['ruNu'] = $p_userType;
					
					// get packages information
					$row_data_temp['packages'][] = $package['name'];
					$row_data_temp['isVip'] = $package['isVip'];
					
										
					// get events / distributions data
					if(count($package['events']) > 0 ) {
						foreach($package['events'] as $key => $event ) {
							
							// if( !isset( $packageType_events[ $event['eventId'] ] ) ) {
							if( !in_array( $event['eventId'] , $eventIds ) ) {
								$event_data = [];
								
								// get event time
								$event_time = substr($event['eventDate'], strpos($event['eventDate'], " ") + 1); 
								// remove seconds
								$event_time = substr($event_time, 0 , 5); 
								
								$event_data['distributionId'] = $event['id'];
                                $event_data['to_distribute'] = $event['to_distribute'];
                                $event_data['is_from_admin_pool'] = $event['is_from_admin_pool'];
								$event_data['eventDistributionIds'] = $event['id'];
								$event_data['eventId'] = $event['eventId'];								
								$event_data['isEmailSend'] = $event['isEmailSend'];
								$event_data['isPublish'] = $event['isPublish'];
								$event_data['mailingDate'] = $event['mailingDate'];
								
								$event_data['totalSubscriptions'] = 1;
								$event_data['totalSentSubscriptions'] = $event['isEmailSend'];
								
								// echo "<pre>"; die(print_r($event['status']['name']));
																
								if( $event['isNoTip'] ) {
									$event_data['eventInfo'] = 'NO TIP';
								} else {
									if( isset($event['status']) && $event['status'] && isset($event['status']['name']) ) {
										$event_status = $event['status']['name'];
									} else {
										$event_status = '???';
									}
									if( $event['result'] != '' ) {
										$event_result = $event['result'];
									} else {
										$event_result = ' - ';
									}
									$event_data['eventInfo'] = '<span class="distribution-event-container"><span class="dist-event-date">' . $event_time. '</span> | <span class="dist-event-teams">' .$event['homeTeam'].' - '.$event['awayTeam']. '</span> | <span class="dist-event-predictions">' .$event['predictionName']. '</span> | <span class="dist-event-result">'.$event_result.'</span> | <span class="dist-event-status '.$event_status . '">'.$event_status . '</span></span>';
								}
								
								// $packageType_events[ $event['eventId'] ] = $event_data;
								$packageType_events[] = $event_data;
								$eventIds[] = $event['eventId'];
								
							} else {
								// if the event already exists, then it means that we have multiple distributions in this packageType for this event
								foreach( $packageType_events as &$eEvent) {
									if( $event['eventId'] == $eEvent['eventId'] ) {
										$eEvent['eventDistributionIds'] .= ','.$event['id'];
										
										$eEvent['totalSubscriptions']++;
										$eEvent['totalSentSubscriptions'] += $event['isEmailSend'];
										break;										
									}
								}
								
								// if the event / distribution is already added , we'll need to "aggregate" some of the information ( ex: isEmailSent )
								// if( $packageType_events[ $event['eventId'] ]['isEmailSend'] != $event['isEmailSend'] ) {
									// $packageType_events[ $event['eventId'] ]['isEmailSend'] = 0;
								// }
																
							}
														
							// get the distributionIds 
							// if( !isset( $row_data_temp['distributionsIds'][ $event['id'] ] ) ) {
								// $row_data_temp['distributionsIds'][] = $event['id'];
							// }
														
						}
						
					}
					
				}
				
				
				$row_data_temp['events'] = $packageType_events;
				$row_data_temp['eventsCount'] = count($packageType_events);
				// get the 'overall' values for published and emails sent
				$tmp_published = 1;
				$tmp_sent = 0;
				$total_sent_distributions_emails = 0;
				$total_distributions_emails = 0;
				foreach($packageType_events as $event ) {
					if( $event['isPublish'] != 1 ) {
						$tmp_published = 0;
					}
					$total_distributions_emails++;
					if( $event['isEmailSend'] ) {
						$total_sent_distributions_emails++;
					}
				}
				if( $total_sent_distributions_emails == $total_distributions_emails && $total_sent_distributions_emails!=0 ) {
					$tmp_sent = 1;
					
				}
				$row_data_temp['isTotalPublish'] = $tmp_published;
				$row_data_temp['isTotalEmailSend'] = $tmp_sent;
				$row_data_temp['totalEmails'] = $total_distributions_emails;
				$row_data_temp['totalSentEmails'] = $total_sent_distributions_emails;
			
				$row_data_temp['distributionIdsString'] = implode( "," , $row_data_temp['distributionsIds'] );
				$response_data[] = $row_data_temp;
			}
			
		}
		
		// sort the results 
		$real_user_sort = strtolower($r->input('real_user_sort'));
		$vip_user_sort = strtolower($r->input('vip_user_sort'));
		$emails_sort =  strtolower($r->input('emails_sort'));
		
		if( $real_user_sort == 'ru' || $real_user_sort == 'nu' ) {
			// $emails_sort = 0;
			// $vip_user_sort = 0;
			
			usort($response_data, function ($a, $b) use ($real_user_sort) {
				if( strtolower($a['ruNu']) == $real_user_sort && strtolower($b['ruNu']) != $real_user_sort ) {
					return -1;
				} elseif( $a['ruNu'] == $b['ruNu'] ) {
					return 0;
				} else {
					return 1; 
				}
			});			
		}
		
		if( $vip_user_sort == 'notvip' || $vip_user_sort == 'vip' ) {	
			// $emails_sort = 0;
			
			usort($response_data, function ($a, $b) use ($vip_user_sort) {
				if( $vip_user_sort == 'vip' ) {
					return $b['isVip'] - $a['isVip'];
				} else {
					return $a['isVip'] - $b['isVip'];
				}				
			});			
		}
		
		if( $emails_sort == 'sent' || $emails_sort == 'unsent' ) {					
			
			usort($response_data, function ($a, $b) use ($emails_sort) {
				
				if( $emails_sort == 'sent' ) {
					if( $a['isTotalEmailSend'] && !$b['isTotalEmailSend'] ) {
						return -1;
					} elseif( $a['isTotalEmailSend'] == $b['isTotalEmailSend'] ) {
						return 0;
					} else {
						return 1; 
					}
				} else {
					if( $a['isTotalEmailSend'] && !$b['isTotalEmailSend'] ) {
						return 1;
					} elseif( $a['isTotalEmailSend'] == $b['isTotalEmailSend'] ) {
						return 0;
					} else {
						return -1; 
					}
				}
			});			
		}
		
		// old implementation tries - TO BE DELETED - kept as reference for now 
		/*
		$row_data = [
			'distributionsIds' => [ 1, 2, 3, 9] ,
			'distributionIdsString' => '1, 2, 3, 9' ,
			'ruNu' => 'RU' ,
			'siteName' => 'GoForWinners' ,
			'siteId' => '1' ,
			'type' => 'tip_1' ,
			'isVip' => '1' ,
			'packages' => [ '3 Days' , '30 Days' ] ,
			'events' => [ eventsData ] , // distributions
		]
		$eventsData = [
			'eventInfo' => '19:00 | Vila Nova GO - Londrina PR | bothToScore-GoForWinners' ,
			'isPublished' => '1' ,
			'isEmailSend' => '1' ,
			'eventId' => '1' ,
			'distributionId' => '1' ,
		]
		
		*/
		// dd($response_data);
		// echo "<pre>";die(print_r($response_data));
		// return $data;
		// die();
		
		// prepare the site data 	
		/*
		foreach( $data as $site_data ) {
			$row_data_temp = [];
			$row_data_temp['siteName'] = $site_data['siteName'];
			$row_data_temp['siteId'] = $site_data['siteId'];
			$row_data_temp['userType'] = '';
			$row_data_temp['eventId'] = '';
			$row_data_temp['isVip'] = '';
			$row_data_temp['rowSpan'] = '';
			$row_data_temp['siteNameCell'] = '';
			$row_data_temp['userTypeCell'] = '';
			$row_data_temp['packageTypeCell'] = '';
			$row_data_temp['packageTypeContent'] = '';
			$row_data_temp['packageTypeName'] = '';
			$row_data_temp['eventContent'] = '';
			$row_data_temp['isEmailSend'] = 0;
			$row_data_temp['isPublish'] = 0;
			$row_data_temp['sentContent'] = '';
			$row_data_temp['emailStatusContent'] = '';
			$row_data_temp['publishedContent'] = '';
			
			// there are distributions without packages
			if( !isset($site_data['packageTypes']) ) {
				$response_data[] = $row_data_temp;
				continue;
			}
			
			foreach($site_data['packageTypes'] as $identifier => $tipIdentifierPackages ) {
				$row_data_temp['packageTypeName'] = $identifier;
				$row_data_temp['packageTypeContent'] = '';
								
				$packageType_events = [];
				
				// grup the events 
				foreach($tipIdentifierPackages['packages'] as $package ) {
					// prepare package related data 
					$p_userType = '';
					// get the RU/NU property
					$userType = \App\PackageSection::where('packageId', $package['id'])
							->where('systemDate', $date)
							->first();					
					if( $userType ) {
						$p_userType = strtoupper($userType->section);
					}
					
					$p_name = $package['name'];
					$p_isVip = $package['isVip'];
					
					if(count($package['events']) > 0 ) {
						foreach($package['events'] as $key => $event ) {
							
							if( !isset( $packageType_events[ $event['eventId'] ] ) ) {
								// new event in packageType
								$event_data = $row_data_temp;
								
								// prepare the data 
								// get event time
								$event_time = substr($event['eventDate'], strpos($event['eventDate'], " ") + 1); 
								// remove seconds
								$event_time = substr($event_time, 0 , 5); 
								// $event_time = date("H:i",strtotime($event['eventDate']));
								
								$event_data['distributionId'] = $event['id'];
								$event_data['eventId'] = $event['eventId'];
								$event_data['isEmailSend'] = $event['isEmailSend'];
								$event_data['isPublish'] = $event['isPublish'];
								$event_data['packageTypeContent'] = $p_name;
								$event_data['userType'] = $p_userType;
								$event_data['isVip'] = $p_isVip;
								
								if( $event['isNoTip'] ) {
									$event_data['eventContent'] = 'NO TIP';
								} else {									
									$event_data['eventContent'] = $event_time . ' | ' . $event['homeTeam'] . ' - ' . $event['awayTeam'] . ' | ' . $event['predictionName'];
								}
								
								if( $event['isEmailSend'] ) {
									$event_data['sentContent'] = '<span class="label label-sm label-success">'.$event['mailingDate'].'</span>';
								} else {
									$event_data['sentContent'] = '<span class="label label-sm label-danger">'.$event['mailingDate'].'</span>';
								}
								
								if( $event['isEmailSend'] ) {
									$event_data['emailStatusContent'] = '<span class="label label-sm label-success">Received</span>';
								} else {
									$event_data['emailStatusContent'] = '<span class="label label-sm label-info">Waiting</span>';
								}
								
								if( $event['isPublish'] ) {
									$event_data['publishedContent'] = '<span class="label label-sm label-success">Published</span>';
								} else {
									$event_data['publishedContent'] = '<span class="label label-sm label-danger">Unpublished</span>';
								}
								
								$packageType_events[ $event['eventId'] ] = $event_data;
							} else {
								// merge the events data 
								$packageType_events[ $event['eventId'] ]['packageTypeContent'] .= '<br>'.$p_name;
								
							}
						}
					} else {
						// no event set 
						$event_data = $row_data_temp;
						$event_data['eventContent'] = '--- No events distributed in package ---';
						$event_data['packageTypeContent'] = $p_name;
						$event_data['userType'] = $p_userType;
						$event_data['isVip'] = $p_isVip;
						$event_data['userTypeCell'] = '<td '.$event_data['rowSpan'].' class="distribution-user">'.$p_userType.'</td>';
						$event_data['siteNameCell'] = '<td '.$event_data['rowSpan'].' class="distribution-site">'.$event_data['siteName'].'</td>';
						$event_data['packageTypeCell'] = '<td '.$event_data['rowSpan'].' class="distribution-tip">'; 
						$event_data['packageTypeCell'] .= '<span class="popovers" data-trigger="hover" data-container=".distribution-event" data-html="true" ';
						$event_data['packageTypeCell'] .= 'data-content="'.$event_data['packageTypeContent'].'" >'.$event_data['packageTypeName'].'</span></td>';
										
						// add to results array
						$response_data[] = $event_data;
					}
				}
				
				$is_first = true;
				// add the events to the results array
				foreach($packageType_events as $eventId => $packageType_event ) {
					if( $is_first ) {
						$is_first = false;
						
						$packageType_event['rowSpan'] = 'rowspan="'.count($packageType_events).'"';
						$packageType_event['userType'] = $packageType_event['userType'];
						$packageType_event['isVip'] = $packageType_event['isVip'];
								
						$packageType_event['userTypeCell'] = '<td '.$packageType_event['rowSpan'].' class="distribution-user">'.$packageType_event['userType'].'</td>';
						$packageType_event['siteNameCell'] = '<td '.$packageType_event['rowSpan'].' class="distribution-site">'.$packageType_event['siteName'].'</td>';
						$packageType_event['packageTypeCell'] = '<td '.$packageType_event['rowSpan'].' class="distribution-tip">'; 
						$packageType_event['packageTypeCell'] .= '<span class="popovers" data-trigger="hover" data-container=".distribution-event" data-html="true" ';
						$packageType_event['packageTypeCell'] .= 'data-content="'.$packageType_event['packageTypeContent'].'" >'.$packageType_event['packageTypeName'].'</span></td>';
								
					} else {
						$packageType_event['userType'] = $packageType_event['userType'];
						$packageType_event['isVip'] = $packageType_event['isVip'];
						
						$packageType_event['userTypeCell'] = '';
						$packageType_event['siteNameCell'] = '';
						$packageType_event['packageTypeCell'] = '';
					}
					$response_data[] = $packageType_event;
					
				}
				
			} // end packageTypes loop
			
		}
		
		
		// sort the results 
		$real_user_sort = strtolower($r->input('real_user_sort'));
		$vip_user_sort = strtolower($r->input('vip_user_sort'));
		$emails_sort =  strtolower($r->input('emails_sort'));
		
		if( $real_user_sort == 'ru' || $real_user_sort == 'nu' ) {
			// $emails_sort = 0;
			// $vip_user_sort = 0;
			
			usort($response_data, function ($a, $b) use ($real_user_sort) {
				if( strtolower($a['userType']) == $real_user_sort && strtolower($b['userType']) != $real_user_sort ) {
					return -1;
				} elseif( $a['userType'] == $b['userType'] ) {
					return 0;
				} else {
					return 1; 
				}
			});			
		}
		
		if( $vip_user_sort == 'notvip' || $vip_user_sort == 'vip' ) {	
			// $emails_sort = 0;
			
			usort($response_data, function ($a, $b) use ($vip_user_sort) {
				if( $vip_user_sort == 'vip' ) {
					return $b['isVip'] - $a['isVip'];
				} else {
					return $a['isVip'] - $b['isVip'];
				}				
			});			
		}
		
		if( $emails_sort == 'sent' || $emails_sort == 'unsent' ) {					
			
			usort($response_data, function ($a, $b) use ($emails_sort) {
				
				if( $emails_sort == 'sent' ) {
					if( $a['isEmailSend'] && !$b['isEmailSend'] ) {
						return -1;
					} elseif( $a['isEmailSend'] == $b['isEmailSend'] ) {
						return 0;
					} else {
						return 1; 
					}
				} else {
					if( $a['isEmailSend'] && !$b['isEmailSend'] ) {
						return 1;
					} elseif( $a['isEmailSend'] == $b['isEmailSend'] ) {
						return 0;
					} else {
						return -1; 
					}
				}
			});			
		}
		*/
		
		
		
        return $response_data;
        // return $data;
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

        if (!$ids)
            return [
                "type" => "error",
                "message" => "No events provided!",
            ];

        $deleted = 0;
        $message = '';
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

            \App\Distribution::where("eventId", "=", $distribution->eventId)
                ->where("provider", "=", "autounit")
                ->where("tipIdentifier", "=", $distribution->tipIdentifier)
                ->delete();
            $distributions = \App\Distribution::where("eventId", "=", $distribution->eventId)
                ->get();

            // delete association if no event is associated with a site
            if (!count($distributions)) {
                \App\Association::where("eventId", "=", $distribution->eventId)
                    ->where("provider", "=", "autounit")
                    ->delete();
            }
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
