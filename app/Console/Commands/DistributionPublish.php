<?php namespace App\Console\Commands;

use App\Distribution;
use App\Site;
use Ixudra\Curl\Facades\Curl;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class DistributionPublish extends CronCommand
{
    protected $name = 'publish:distribution';
    protected $description = 'Checks, schedules and publishes distribution events';
    /** @var array $distribution */
    protected $distribution = [];
    /** @var array $systemDate */
    protected $systemDate = [];
    /** @var int $minute */
    protected $minute;
    /** @var int $hour */
    protected $hour;
    /** @var int $timestamp */
    protected $timestamp;

    private $log = null;
    private $currentDate = null;

    public function fire()
    {
        $this->timestamp = time();
        $this->systemDate = [
            'today' => gmdate('Y-m-d'),
            'yesterday' => gmdate('Y-m-d', time() - (24 * 60 * 60))
        ];

        $this->minute = intval(gmdate('i'));
        $this->hour = intval(gmdate('G'));

        //$cron = $this->startCron();

        $this->currentDate = date("Y-m-d", time());

        $events = $this->loadData();

        if (!$events) {
            //$this->stopCron($cron, []);
            return true;
        }
        $dataInfo = [
            'sent' => 0,
            'errors' => []
        ];
        foreach($events as $siteId => $values) {
            $site = Site::find($siteId);
        
            $this->log = new Logger($this->currentDate . '_automatic_publish');
            $this->log->pushHandler(new StreamHandler(storage_path('logs/' . $this->currentDate . '_site-'. $siteId . '_automatic_publish.log')), Logger::INFO);

            if (!$site) {
                $dataInfo['errors'][] = "Couldn't find site with id $siteId";
                continue;
            }
            foreach($values as $systemDate => $info) {
                if ($info['allEventsPublished']) {
                    $this->log->log(100, json_encode([
                        "systemDate"        => $systemDate,
                        "site"              => $site->name,
                        "message"           => "All events were published for this site and date, skipping date",
                        "step"              => 7
                    ]));
                    continue;
                }

                // process any events that have a publish time lower than actual time
                // OR has already events published in the respective day

                foreach($info['events'] as $event) {
                    if (!$event->publishTime) {
                        $event->publishTime = $info['publishTime'];
                        $event->update();
                    }
                    if (!$info['hasPublishedEvents'] && ($event->publishTime == 0)) {
                        $this->log->log(100, json_encode([
                            "packageId"             => $event->packageId,
                            "tableIdentifier"       => $event->tableIdentifier,
                            "tipIdentifier"         => $event->tipIdentifier,
                            "match"                 => $event->homeTeam . " - " . $event->awayTeam,
                            "result"                => $event->result,
                            "statusId"              => $event->statusId,
                            "publishTime"           => $event->publishTime,
                            "hasPublishedEvents"    => $info['hasPublishedEvents'],
                            "systemDate"            => $systemDate,
                            "currentTime"           => $this->timestamp,
                            "site"                  => $site->name,
                            "message"               => "Publish time of the distribution is lower than current time",
                            "step"                  => 8
                        ]));
                        continue;
                    }

                    if (!$event->isPublish && $event->result && $event->status && $this->timestamp >= $event->publishTime) {
                        if (!$this->publish($site, $event)) {
                            $dataInfo['errors'][] = "Couldn't publish eventId {$event->id} to siteId {$site->id}";
                            $this->log->log(100, json_encode([
                                "packageId"             => $event->packageId,
                                "tableIdentifier"       => $event->tableIdentifier,
                                "tipIdentifier"         => $event->tipIdentifier,
                                "match"                 => $event->homeTeam . " - " . $event->awayTeam,
                                "result"                => $event->result,
                                "statusId"              => $event->statusId,
                                "publishTime"           => $event->publishTime,
                                "hasPublishedEvents"    => $info['hasPublishedEvents'],
                                "systemDate"            => $systemDate,
                                "currentTime"           => $this->timestamp,
                                "site"                  => $site->name,
                                "message"               => "Could not publish event",
                                "step"                  => 9
                            ]));
                        }
                        else {
                            if (!isset($dataInfo['sent']))
                                $dataInfo['sent'] = 0;
                            $dataInfo['sent']++;

                            $this->log->log(100, json_encode([
                                "packageId"             => $event->packageId,
                                "tableIdentifier"       => $event->tableIdentifier,
                                "tipIdentifier"         => $event->tipIdentifier,
                                "match"                 => $event->homeTeam . " - " . $event->awayTeam,
                                "result"                => $event->result,
                                "statusId"              => $event->statusId,
                                "publishTime"           => $event->publishTime,
                                "hasPublishedEvents"    => $info['hasPublishedEvents'],
                                "systemDate"            => $systemDate,
                                "currentTime"           => $this->timestamp,
                                "site"                  => $site->name,
                                "message"               => "Published event with current time",
                                "step"                  => 10
                            ]));
                        }
                    }
                }

                if (!$info['hasPublishedEvents']) {
                    $this->log->log(100, json_encode([
                        "hasPublishedEvents"    => $info['hasPublishedEvents'],
                        "systemDate"            => $systemDate,
                        "currentTime"           => $this->timestamp,
                        "site"                  => $site->name,
                        "message"               => "Site has not published events",
                        "step"                  => 11
                    ]));
                    if ($info['hasPendingEvents']) {
                        $this->log->log(100, json_encode([
                            "hasPendingEvents"      => $info['hasPendingEvents'],
                            "hasPublishedEvents"    => $info['hasPublishedEvents'],
                            "systemDate"            => $systemDate,
                            "currentTime"           => $this->timestamp,
                            "site"                  => $site->name,
                            "message"               => "Site has pending events",
                            "step"                  => 12
                        ]));
                        continue;
                    }

                    if ($systemDate === $this->systemDate['yesterday']) {
                        $this->log->log(100, json_encode([
                            "hasPendingEvents"      => $info['hasPendingEvents'],
                            "hasPublishedEvents"    => $info['hasPublishedEvents'],
                            "systemDate"            => $systemDate,
                            "currentTime"           => $this->timestamp,
                            "site"                  => $site->name,
                            "winRate"               => $info['winRate'],
                            "message"               => "Processing yesterday events",
                            "step"                  => 13
                        ]));
                        // process events that weren't finished yesterday

                        if ($info['winRate'] >= 50) {
                            foreach($info['events'] as $event) {
                                if ($event->isPublish) {
                                    $this->log->log(100, json_encode([
                                        "packageId"             => $event->packageId,
                                        "tableIdentifier"       => $event->tableIdentifier,
                                        "tipIdentifier"         => $event->tipIdentifier,
                                        "match"                 => $event->homeTeam . " - " . $event->awayTeam,
                                        "result"                => $event->result,
                                        "statusId"              => $event->statusId,
                                        "isPublished"           => $event->isPublish,
                                        "publishTime"           => $event->publishTime,
                                        "hasPublishedEvents"    => $info['hasPublishedEvents'],
                                        "systemDate"            => $systemDate,
                                        "site"                  => $site->name,
                                        "message"               => "Event was already published for yesterday case",
                                        "step"                  => 14
                                    ]));
                                    continue;
                                }

                                if (!$this->publish($site, $event)) {
                                    $dataInfo['errors'][] = "Couldn't publish eventId {$event->id} to siteId {$site->id}";
                                    $this->log->log(100, json_encode([
                                        "packageId"             => $event->packageId,
                                        "tableIdentifier"       => $event->tableIdentifier,
                                        "tipIdentifier"         => $event->tipIdentifier,
                                        "match"                 => $event->homeTeam . " - " . $event->awayTeam,
                                        "result"                => $event->result,
                                        "statusId"              => $event->statusId,
                                        "isPublished"           => $event->isPublish,
                                        "publishTime"           => $event->publishTime,
                                        "hasPublishedEvents"    => $info['hasPublishedEvents'],
                                        "systemDate"            => $systemDate,
                                        "site"                  => $site->name,
                                        "message"               => "Failed to publish event",
                                        "step"                  => 15
                                    ]));
                                }
                                else {
                                    $dataInfo['sent']++;
                                    $this->log->log(100, json_encode([
                                        "packageId"             => $event->packageId,
                                        "tableIdentifier"       => $event->tableIdentifier,
                                        "tipIdentifier"         => $event->tipIdentifier,
                                        "match"                 => $event->homeTeam . " - " . $event->awayTeam,
                                        "result"                => $event->result,
                                        "statusId"              => $event->statusId,
                                        "isPublished"           => $event->isPublish,
                                        "publishTime"           => $event->publishTime,
                                        "hasPublishedEvents"    => $info['hasPublishedEvents'],
                                        "systemDate"            => $systemDate,
                                        "site"                  => $site->name,
                                        "message"               => "Event published with current date",
                                        "step"                  => 16
                                    ]));
                                }
                            }
                        } else {
                           if ($info['publishTime'] && $info['publishTime'] >= $this->timestamp) {
                                $this->log->log(100, json_encode([
                                    "sitePublishTime"       => $info['publishTime'],
                                    "hasPublishedEvents"    => $info['hasPublishedEvents'],
                                    "systemDate"            => $systemDate,
                                    "site"                  => $site->name,
                                    "message"               => "Trying to publish events with site publish time",
                                    "step"                  => 17
                                ]));
                               foreach($info['events'] as $event) {
                                   if ($event->isPublish) {
                                        $this->log->log(100, json_encode([
                                            "packageId"             => $event->packageId,
                                            "tableIdentifier"       => $event->tableIdentifier,
                                            "tipIdentifier"         => $event->tipIdentifier,
                                            "match"                 => $event->homeTeam . " - " . $event->awayTeam,
                                            "result"                => $event->result,
                                            "statusId"              => $event->statusId,
                                            "isPublished"           => $event->isPublish,
                                            "sitePublishTime"       => $info['publishTime'],
                                            "eventPublishTime"      => $event->publishTime,
                                            "hasPublishedEvents"    => $info['hasPublishedEvents'],
                                            "systemDate"            => $systemDate,
                                            "site"                  => $site->name,
                                            "message"               => "Event was already published, skipping",
                                            "step"                  => 18
                                        ]));
                                       continue;
                                   }

                                   if (!$this->publish($site, $event)) {
                                       $dataInfo['errors'][] = "Couldn't publish eventId {$event->id} to siteId {$site->id}";
                                       $this->log->log(100, json_encode([
                                            "packageId"             => $event->packageId,
                                            "tableIdentifier"       => $event->tableIdentifier,
                                            "tipIdentifier"         => $event->tipIdentifier,
                                            "match"                 => $event->homeTeam . " - " . $event->awayTeam,
                                            "result"                => $event->result,
                                            "statusId"              => $event->statusId,
                                            "isPublished"           => $event->isPublish,
                                            "sitePublishTime"       => $info['publishTime'],
                                            "eventPublishTime"      => $event->publishTime,
                                            "hasPublishedEvents"    => $info['hasPublishedEvents'],
                                            "systemDate"            => $systemDate,
                                            "site"                  => $site->name,
                                            "message"               => "Failed to publish event",
                                            "step"                  => 19
                                        ]));
                                   }
                                   else {
                                       $dataInfo['sent']++;
                                       $this->log->log(100, json_encode([
                                            "packageId"             => $event->packageId,
                                            "tableIdentifier"       => $event->tableIdentifier,
                                            "tipIdentifier"         => $event->tipIdentifier,
                                            "match"                 => $event->homeTeam . " - " . $event->awayTeam,
                                            "result"                => $event->result,
                                            "statusId"              => $event->statusId,
                                            "isPublished"           => $event->isPublish,
                                            "sitePublishTime"       => $info['publishTime'],
                                            "eventPublishTime"      => $event->publishTime,
                                            "hasPublishedEvents"    => $info['hasPublishedEvents'],
                                            "systemDate"            => $systemDate,
                                            "site"                  => $site->name,
                                            "message"               => "Event was published with current date",
                                            "step"                  => 20
                                        ]));
                                   }
                               }
                           } else {
                               if (!$info['publishTime']) {
                                   $info['publishTime'] = strtotime('today 06:00:00') + mt_rand(0, 30 * 60);

                                   $this->log->log(100, json_encode([
                                        "sitePublishTime"       => $info['publishTime'],
                                        "hasPublishedEvents"    => $info['hasPublishedEvents'],
                                        "systemDate"            => $systemDate,
                                        "site"                  => $site->name,
                                        "message"               => "Set site publish time, will try to publish events with this timestamp [today 06:00:00 + (0 <-> 30 min)]",
                                        "step"                  => 21
                                    ]));
                               }

                               foreach ($info['events'] as $event) {
                                    $this->log->log(100, json_encode([
                                        "packageId"             => $event->packageId,
                                        "tableIdentifier"       => $event->tableIdentifier,
                                        "tipIdentifier"         => $event->tipIdentifier,
                                        "match"                 => $event->homeTeam . " - " . $event->awayTeam,
                                        "result"                => $event->result,
                                        "statusId"              => $event->statusId,
                                        "isPublished"           => $event->isPublish,
                                        "sitePublishTime"       => $info['publishTime'],
                                        "eventPublishTime"      => $event->publishTime,
                                        "hasPublishedEvents"    => $info['hasPublishedEvents'],
                                        "systemDate"            => $systemDate,
                                        "site"                  => $site->name,
                                        "message"               => "Processing event",
                                        "step"                  => 22
                                    ]));
                                    if ($event->isPublish) {
                                        $this->log->log(100, json_encode([
                                            "packageId"             => $event->packageId,
                                            "tableIdentifier"       => $event->tableIdentifier,
                                            "tipIdentifier"         => $event->tipIdentifier,
                                            "match"                 => $event->homeTeam . " - " . $event->awayTeam,
                                            "result"                => $event->result,
                                            "statusId"              => $event->statusId,
                                            "isPublished"           => $event->isPublish,
                                            "sitePublishTime"       => $info['publishTime'],
                                            "eventPublishTime"      => $event->publishTime,
                                            "hasPublishedEvents"    => $info['hasPublishedEvents'],
                                            "systemDate"            => $systemDate,
                                            "site"                  => $site->name,
                                            "message"               => "Event was already published",
                                            "step"                  => 23
                                        ]));
                                        continue;
                                   }

                                    if (!$event->publishTime) {
                                        $event->publishTime = $info['publishTime'];
                                        $event->save();

                                        $this->log->log(100, json_encode([
                                            "packageId"             => $event->packageId,
                                            "tableIdentifier"       => $event->tableIdentifier,
                                            "tipIdentifier"         => $event->tipIdentifier,
                                            "match"                 => $event->homeTeam . " - " . $event->awayTeam,
                                            "result"                => $event->result,
                                            "statusId"              => $event->statusId,
                                            "isPublished"           => $event->isPublish,
                                            "sitePublishTime"       => $info['publishTime'],
                                            "eventPublishTime"      => $event->publishTime,
                                            "hasPublishedEvents"    => $info['hasPublishedEvents'],
                                            "systemDate"            => $systemDate,
                                            "site"                  => $site->name,
                                            "message"               => "Updated event publishTime with site publishTime",
                                            "step"                  => 24
                                        ]));
                                    }
                               }
                           }
                        }
                    } else {
                        $this->log->log(100, json_encode([
                            "hasPendingEvents"      => $info['hasPendingEvents'],
                            "hasPublishedEvents"    => $info['hasPublishedEvents'],
                            "systemDate"            => $systemDate,
                            "currentTime"           => $this->timestamp,
                            "site"                  => $site->name,
                            "winRate"               => $info['winRate'],
                            "message"               => "Processing today events",
                            "step"                  => 25
                        ]));
                        // process events that for today
                        if ($this->hour < getenv('PUBLISH_EVENTS_ON_WIN_START') || $info['hasPendingEvents']) {
                            $this->log->log(100, json_encode([
                                "hasPendingEvents"      => $info['hasPendingEvents'],
                                "hasPublishedEvents"    => $info['hasPublishedEvents'],
                                "systemDate"            => $systemDate,
                                "currentTime"           => $this->timestamp,
                                "site"                  => $site->name,
                                "winRate"               => $info['winRate'],
                                "currentHour"           => $this->hour,
                                "publishTimeHour"       => getenv('PUBLISH_EVENTS_ON_WIN_START'),
                                "message"               => "Current hour is lower than publish time hour OR we have pending events",
                                "step"                  => 26
                            ]));
                            continue;
                        }

                        if (!$info['publishTime']) {
                            $this->log->log(100, json_encode([
                                "hasPendingEvents"      => $info['hasPendingEvents'],
                                "hasPublishedEvents"    => $info['hasPublishedEvents'],
                                "systemDate"            => $systemDate,
                                "currentTime"           => $this->timestamp,
                                "site"                  => $site->name,
                                "winRate"               => $info['winRate'],
                                "currentHour"           => $this->hour,
                                "publishTimeHour"       => getenv('PUBLISH_EVENTS_ON_WIN_START'),
                                "message"               => "Publish Time is not SET",
                                "step"                  => 300
                            ]));

                            if ($info['winRate'] >= 50) {
                                $info['publishTime'] = strtotime('today ' . getenv('PUBLISH_EVENTS_ON_WIN_START') . ':00:00') + mt_rand(0, 2 * 60 * 60);

                                foreach($info['events'] as $event) {
                                    if ($this->timestamp > $info['publishTime']) {
                                        $event->publishTime = $info['publishTime'];
                                        $event->update();
                                        continue;
                                    }

                                    if ($event->isPublish) {
                                        $this->log->log(100, json_encode([
                                            "packageId"             => $event->packageId,
                                            "tableIdentifier"       => $event->tableIdentifier,
                                            "tipIdentifier"         => $event->tipIdentifier,
                                            "match"                 => $event->homeTeam . " - " . $event->awayTeam,
                                            "result"                => $event->result,
                                            "statusId"              => $event->statusId,
                                            "isPublished"           => $event->isPublish,
                                            "sitePublishTime"       => $info['publishTime'],
                                            "eventPublishTime"      => $event->publishTime,
                                            "hasPublishedEvents"    => $info['hasPublishedEvents'],
                                            "systemDate"            => $systemDate,
                                            "site"                  => $site->name,
                                            "message"               => "Event was already published",
                                            "step"                  => 27
                                        ]));
                                        continue;
                                    }

                                    if (!$this->publish($site, $event)) {
                                        $dataInfo['errors'][] = "Couldn't publish eventId {$event->id} to siteId {$site->id}";
                                        $this->log->log(100, json_encode([
                                            "packageId"             => $event->packageId,
                                            "tableIdentifier"       => $event->tableIdentifier,
                                            "tipIdentifier"         => $event->tipIdentifier,
                                            "match"                 => $event->homeTeam . " - " . $event->awayTeam,
                                            "result"                => $event->result,
                                            "statusId"              => $event->statusId,
                                            "isPublished"           => $event->isPublish,
                                            "sitePublishTime"       => $info['publishTime'],
                                            "eventPublishTime"      => $event->publishTime,
                                            "hasPublishedEvents"    => $info['hasPublishedEvents'],
                                            "systemDate"            => $systemDate,
                                            "site"                  => $site->name,
                                            "message"               => "Failed to publish event",
                                            "step"                  => 28
                                        ]));
                                    }
                                    else {
                                        $dataInfo['sent']++;

                                        $this->log->log(100, json_encode([
                                            "packageId"             => $event->packageId,
                                            "tableIdentifier"       => $event->tableIdentifier,
                                            "tipIdentifier"         => $event->tipIdentifier,
                                            "match"                 => $event->homeTeam . " - " . $event->awayTeam,
                                            "result"                => $event->result,
                                            "statusId"              => $event->statusId,
                                            "isPublished"           => $event->isPublish,
                                            "sitePublishTime"       => $info['publishTime'],
                                            "eventPublishTime"      => $event->publishTime,
                                            "hasPublishedEvents"    => $info['hasPublishedEvents'],
                                            "systemDate"            => $systemDate,
                                            "site"                  => $site->name,
                                            "message"               => "Published event with current time",
                                            "step"                  => 29
                                        ]));
                                    }
                                }

                                $this->log->log(100, json_encode([
                                    "sitePublishTime"       => $info['publishTime'],
                                    "hasPendingEvents"      => $info['hasPendingEvents'],
                                    "hasPublishedEvents"    => $info['hasPublishedEvents'],
                                    "systemDate"            => $systemDate,
                                    "currentTime"           => $this->timestamp,
                                    "site"                  => $site->name,
                                    "winRate"               => $info['winRate'],
                                    "message"               => "Set publish time for today events [today ]" . getenv('PUBLISH_EVENTS_ON_WIN_START') . ':00:00 + (0 <-> 2 hours)',
                                    "step"                  => 30
                                ]));
                            }
                            else {
                                $info['publishTime'] = strtotime('tomorrow 07:00:00') + mt_rand(0, 30 * 60);

                                $this->log->log(100, json_encode([
                                    "sitePublishTime"       => $info['publishTime'],
                                    "hasPendingEvents"      => $info['hasPendingEvents'],
                                    "hasPublishedEvents"    => $info['hasPublishedEvents'],
                                    "systemDate"            => $systemDate,
                                    "currentTime"           => $this->timestamp,
                                    "site"                  => $site->name,
                                    "winRate"               => $info['winRate'],
                                    "message"               => "Set publish time for today events [tomorrow 07:00:00 + (0 <-> 30 minutes)]",
                                    "step"                  => 31
                                ]));
                            }

                        }
                        foreach ($info['events'] as $event) {
                            if ($event->isPublish) {
                                $this->log->log(100, json_encode([
                                    "packageId"             => $event->packageId,
                                    "tableIdentifier"       => $event->tableIdentifier,
                                    "tipIdentifier"         => $event->tipIdentifier,
                                    "match"                 => $event->homeTeam . " - " . $event->awayTeam,
                                    "result"                => $event->result,
                                    "statusId"              => $event->statusId,
                                    "isPublished"           => $event->isPublish,
                                    "sitePublishTime"       => $info['publishTime'],
                                    "eventPublishTime"      => $event->publishTime,
                                    "hasPublishedEvents"    => $info['hasPublishedEvents'],
                                    "systemDate"            => $systemDate,
                                    "site"                  => $site->name,
                                    "message"               => "Event was already published",
                                    "step"                  => 32
                                ]));
                                continue;
                            }

                            if (!$event->publishTime) {
                                $event->publishTime = $info['publishTime'];
                                $event->save();

                                $this->log->log(100, json_encode([
                                    "packageId"             => $event->packageId,
                                    "tableIdentifier"       => $event->tableIdentifier,
                                    "tipIdentifier"         => $event->tipIdentifier,
                                    "match"                 => $event->homeTeam . " - " . $event->awayTeam,
                                    "result"                => $event->result,
                                    "statusId"              => $event->statusId,
                                    "isPublished"           => $event->isPublish,
                                    "sitePublishTime"       => $info['publishTime'],
                                    "eventPublishTime"      => $event->publishTime,
                                    "hasPublishedEvents"    => $info['hasPublishedEvents'],
                                    "systemDate"            => $systemDate,
                                    "site"                  => $site->name,
                                    "message"               => "Updated event publishTime with site publishTime",
                                    "step"                  => 33
                                ]));
                            }
                        }
                    }
                }
            }
        }
        $this->info(json_encode($dataInfo));
        //$this->stopCron($cron, $dataInfo);
        return true;
    }

    protected function loadData()
    {
        $data = [];
        $distributions = Distribution::whereIn('systemDate', array_values($this->systemDate))
            ->orderBy("siteId", "DESC")
            ->get();

        foreach ($distributions as $value) {
            $matchesCounter = Distribution::where('systemDate', $value->systemDate)
                ->distinct('eventId')
                ->where("siteId", $value->siteId)
                ->count('eventId');

            // skip postponed match if we have more than 1 match on a site
            if ($matchesCounter > 1 && $value->statusId == 4) {
                continue;
            }

            if (!isset($data[$value->siteId])) {
                $data[$value->siteId] = [];
                $this->log = new Logger($this->currentDate . '_automatic_publish');
                $this->log->pushHandler(new StreamHandler(storage_path('logs/' . $this->currentDate . '_site-'. $value->siteId . '_automatic_publish.log')), Logger::INFO);
            }
            if (!isset($data[$value->siteId][$value->systemDate])) {
                $data[$value->siteId][$value->systemDate] = [
                    'allEventsPublished' => false,
                    'hasPublishedEvents' => false,
                    'hasPendingEvents' => false,
                    'publishTime' => 0,
                    'events' => [],
                    'winRate' => 0,
                    'tmp' => [
                        'all' => 0,
                        'good' => 0,
                        'published' => 0
                    ]
                ];
            }

            if ($value->isPublish) {
                $data[$value->siteId][$value->systemDate]['hasPublishedEvents'] = true;
                $data[$value->siteId][$value->systemDate]['tmp']['published']++;

                $this->log->log(100, json_encode([
                    "systemDate"        => $value->systemDate,
                    "siteId"            => $value->siteId,
                    "packageId"         => $value->packageId,
                    "tableIdentifier"   => $value->tableIdentifier,
                    "tipIdentifier"     => $value->tipIdentifier,
                    "match"             => $value->homeTeam . " - " . $value->awayTeam,
                    "result"            => $value->result,
                    "statusId"          => $value->statusId,
                    "publishTime"       => $value->publishTime,
                    "message"           => "Distribution was already published",
                    "step"              => 2
                ]));
            }

            if
            (
                !$data[$value->siteId][$value->systemDate]['hasPendingEvents'] &&
                (!$value->result || !$value->statusId) &&
                $value->statusId != 4
            ) {
                $data[$value->siteId][$value->systemDate]['hasPendingEvents'] = true;
                $this->log->log(100, json_encode([
                    "systemDate"        => $value->systemDate,
                    "siteId"            => $value->siteId,
                    "packageId"         => $value->packageId,
                    "tableIdentifier"   => $value->tableIdentifier,
                    "tipIdentifier"     => $value->tipIdentifier,
                    "match"             => $value->homeTeam . " - " . $value->awayTeam,
                    "result"            => $value->result,
                    "statusId"          => $value->statusId,
                    "publishTime"       => $value->publishTime,
                    "message"           => "Site has pending events",
                    "step"              => 3
                ]));
            }

            if
            (
                !$data[$value->siteId][$value->systemDate]['publishTime'] ||
                $data[$value->siteId][$value->systemDate]['publishTime'] < $value->publishTime
            ) {
                $data[$value->siteId][$value->systemDate]['publishTime'] = $value->publishTime;
                $this->log->log(100, json_encode([
                    "systemDate"        => $value->systemDate,
                    "siteId"            => $value->siteId,
                    "packageId"         => $value->packageId,
                    "tableIdentifier"   => $value->tableIdentifier,
                    "tipIdentifier"     => $value->tipIdentifier,
                    "match"             => $value->homeTeam . " - " . $value->awayTeam,
                    "result"            => $value->result,
                    "statusId"          => $value->statusId,
                    "publishTime"       => $value->publishTime,
                    "message"           => "Site does not have publishTime or distribution has a greater publishTime value",
                    "step"              => 3
                ]));
            }

            if ((int) $value->statusId === 1) {
                $data[$value->siteId][$value->systemDate]['tmp']['good']++;

                $this->log->log(100, json_encode([
                    "systemDate"        => $value->systemDate,
                    "siteId"            => $value->siteId,
                    "packageId"         => $value->packageId,
                    "tableIdentifier"   => $value->tableIdentifier,
                    "tipIdentifier"     => $value->tipIdentifier,
                    "match"             => $value->homeTeam . " - " . $value->awayTeam,
                    "result"            => $value->result,
                    "statusId"          => $value->statusId,
                    "publishTime"       => $value->publishTime,
                    "message"           => "Site has a WIN status event",
                    "step"              => 4
                ]));
            }
            $value->publishTime = $data[$value->siteId][$value->systemDate]['publishTime'];
            $data[$value->siteId][$value->systemDate]['tmp']['all']++;
            $data[$value->siteId][$value->systemDate]['events'][] = $value;
        }

        

        foreach ($data as $siteId => $dates) {
            $this->log = new Logger($this->currentDate . '_automatic_publish');
            $this->log->pushHandler(new StreamHandler(storage_path('logs/' . $this->currentDate . '_site-'. $siteId . '_automatic_publish.log')), Logger::INFO);

            foreach ($dates as $date => $value) {
                $data[$siteId][$date]['winRate'] = round(100 * ($value['tmp']['good'] / $value['tmp']['all']), 2);
                $data[$siteId][$date]['allEventsPublished'] =  $value['tmp']['all'] === $value['tmp']['published'];

                $this->log->log(100, json_encode([
                    "systemDate"            => $date,
                    "siteId"                => $siteId,
                    "winRate"               => $data[$siteId][$date]['winRate'],
                    "allEventsPublished"    => $data[$siteId][$date]['allEventsPublished'],
                    "tmp"                   => $data[$siteId][$date]['tmp'],
                    "message"               => "Site status information",
                    "step"                  => 5
                ]));
                unset($data[$siteId][$date]['tmp']);
            }
        }
        return $data;
    }

    protected function publish(Site $site, Distribution $event) : bool
    {
        $archive = new \App\Http\Controllers\Admin\Archive();
        $result = $archive->publish([$event->id]);

        if ($result['type'] == 'success')
            return true;

        return false;
    }
}
