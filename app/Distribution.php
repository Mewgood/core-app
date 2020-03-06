<?php namespace App;

use App\Models\Odd;
use App\Models\AutoUnit\DailySchedule;
use App\Models\AutoUnit\MonthlySetting;
use Illuminate\Database\Eloquent\Model;
use App\Console\Commands\AutoUnitAddEvents;

class Distribution extends Model {

    protected $table = 'distribution';

    protected $fillable = [
        'associationId',
        'eventId',
        'source',
        'provider',
        'siteId',
        'packageId',
        'tableIdentifier',
        'tipIdentifier',
        'isEmailSend',
        'isPublish',
        'isNoTip',
        'isVip',
        'country',
        'countryCode',
        'league',
        'leagueId',
        'homeTeam',
        'homeTeamId',
        'awayTeam',
        'awayTeamId',
        'odd',
        'predictionId',
        'predictionName',
        'result',
        'statusId',
        'eventDate',
        'systemDate',
        'mailingDate',
        'publishTime',
        'isPublished',
        'to_distribute'
    ];

    // get the status name of distributed event
    public function status()
    {
        return $this->hasOne('App\AppResultStatus', 'id', 'statusId');
    }

    public function site()
    {
        return $this->hasOne('App\Site', 'id', 'siteId');
    }

    public function package()
    {
        return $this->hasOne('App\Package', 'id', 'packageId');
    }

    public function prediction()
    {
        return $this->hasOne('App\Prediction', 'identifier', 'predictionId');
    }

    public function event()
    {
        return $this->hasOne('App\Event', 'id', 'eventId');
    }

    public function archiveHome()
    {
        return $this->hasOne('App\ArchiveHome', 'distributionId');
    }

    public function archiveBig()
    {
        return $this->hasOne('App\ArchiveBig', 'distributionId');
    }

    public static function removeUnused()
    {
        $today = gmdate("Y-m-d");
        $from = strtotime($today . "-6 months"); //unix timestamp
        $from = gmdate("Y-m-d", $from); // Y-m-d string format

        Distribution::where("systemDate", "<", $from)->delete();
    }

    public function updateDistribution($association)
    {
        $data = [];
        $packagePredictions = $this->package->packagePredictions()->get()->toArray();
        // check if the new prediction is in the available predictions of the site package
        if (array_search($association->predictionId, array_column($packagePredictions, 'predictionIdentifier')) !== false) {
            if (!$this->isPublish) {
                self::where("siteId", "=", $this->siteId)
                    ->where("associationId", "=", $this->associationId)
                    ->update([
                        "odd" => $association->odd,
                        "predictionId" => $association->predictionId,
                        "predictionName" => $association->prediction->name
                    ]);

                if ($this->provider != "autounit") {
                    $data = $this->updateSimpleCase($association);
                } else {
                    $data = $this->updateAutounitCase($association);
                }
            } else {
                $data = [
                    "site" => $this->site,
                    "status" => "Published",
                    "type" => ucfirst($this->provider)
                ];
            }
        } else {
            if (!$this->isPublish) {
                if ($this->provider == "autounit") {
                    $data = $this->updateAutounitCase($association);
                } else {
                    self::where("siteId", "=", $this->siteId)
                        ->where("associationId", "=", $this->associationId)
                        ->delete();

                    $data = [
                        "site" => $this->site,
                        "status" => "Removed",
                        "type" => ucfirst($this->provider)
                    ];
                }
            } else {
                $data = [
                    "site" => $this->site,
                    "status" => "Published",
                    "type" => ucfirst($this->provider)
                ];
            }
        }
        return $data;
    }

    public function updatePublishedDistribution($association)
    {
        $data = null;
        $packagePredictions = $this->package->packagePredictions()->get()->toArray();
        // check if the new prediction is in the available predictions of the site package
        if (array_search($association->predictionId, array_column($packagePredictions, 'predictionIdentifier')) !== false) {
            self::where("siteId", "=", $this->siteId)
                ->where("associationId", "=", $this->associationId)
                ->update([
                    "odd" => $association->odd,
                    "predictionId" => $association->predictionId,
                    "predictionName" => $association->prediction->name
                ]);

            if ($this->provider != "autounit") {
                $data = $this->updateSimplePublishedCase($association);
            } else {
                $data = $this->updateAutounitPublishedCase($association);
            }
        } else {
            if ($this->archiveHome) {
                $this->archiveHome()->update([
                    "isVisible" => 0
                ]);
            }
    
            if ($this->archiveBig) {
                $this->archiveBig()->update([
                    "isVisible" => 0
                ]); 
            }

            
            if ($this->provider != "autounit") {
                self::where("siteId", "=", $this->siteId)
                    ->where("associationId", "=", $this->associationId)
                    ->delete();

                $data = [
                    "site" => $this->site,
                    "status" => "Removed",
                    "type" => ucfirst($this->provider)
                ];
            } else {
                $data = $this->changePublishedAutounit();
            }
        }
        return $data;
    }

    private function updateSimpleCase($association)
    {
        $this->event->odd = $association->odd;
        $this->event->predictionId = $association->predictionId;
        $this->event->update();

        $statusByScore = new \App\Src\Prediction\SetStatusByScore($this->event->result, $this->event->predictionId);
        $statusByScore->evaluateStatus();
        $statusId = $statusByScore->getStatus();
        
        if ($statusId > 0) {
            $eventInstance = new \App\Http\Controllers\Admin\Event();
            $eventInstance->updateResultAndStatus($this->event->id, $this->event->result, $statusId);
            self::where("siteId", "=", $this->siteId)
                ->where("associationId", "=", $this->associationId)
                ->update([
                    "statusId" => $statusId
                ]);
            $association->statusId = $statusId;
            $association->update();
        }

        $data = [
            "site" => $this->site,
            "status" => "Updated",
            "type" => ucfirst($this->provider)
        ];
        return $data;
    }

    private function updateSimplePublishedCase($association)
    {
        $this->event->odd = $association->odd;
        $this->event->predictionId = $association->predictionId;
        $this->event->update();

        $statusByScore = new \App\Src\Prediction\SetStatusByScore($this->event->result, $this->event->predictionId);
        $statusByScore->evaluateStatus();
        $statusId = $statusByScore->getStatus();
        
        if ($statusId > 0) {
            $eventInstance = new \App\Http\Controllers\Admin\Event();
            $eventInstance->updateResultAndStatus($this->event->id, $this->event->result, $statusId);
            self::where("siteId", "=", $this->siteId)
                ->where("associationId", "=", $this->associationId)
                ->update([
                    "statusId" => $statusId
                ]);
            $association->statusId = $statusId;
            $association->update();
        }

        if ($this->archiveHome) {
            $this->archiveHome()->update([
                "odd" => $association->odd,
                "predictionId" => $association->predictionId,
                "predictionName" => $association->prediction->name,
                "statusId" => $association->statusId
            ]);
        }

        if ($this->archiveBig) {
            $this->archiveBig()->update([
                "odd" => $association->odd,
                "predictionId" => $association->predictionId,
                "predictionName" => $association->prediction->name,
                "statusId" => $association->statusId
            ]); 
        }

        $data = [
            "site" => $this->site,
            "status" => "Updated",
            "type" => ucfirst($this->provider)
        ];
        return $data;
    }

    private function updateAutounitCase($association)
    {
        $data = null;
        
        $autounitDailySchedule = DailySchedule::where("siteId", "=", $this->siteId)
            ->where("tableIdentifier", "=", $this->tableIdentifier)
            ->where("tipIdentifier", "=", $this->tipIdentifier)
            ->where("match_id", "=", $this->event->match->primaryId)
            ->where("systemDate", "=", $this->systemDate)
            ->first();

        $autounitConfiguration = MonthlySetting::where("siteId", "=", $this->siteId)
            ->where("tableIdentifier", "=", $this->tableIdentifier)
            ->where("tipIdentifier", "=", $this->tipIdentifier)
            ->whereRaw("date = DATE_FORMAT('" . $this->systemDate . "', '%Y-%m')")
            ->first();

        if ($autounitDailySchedule) {
            if (
                strtolower($autounitDailySchedule->predictionGroup) == strtolower($association->prediction->group) &&
                $autounitConfiguration->minOdd <= $association->odd &&
                $autounitConfiguration->maxOdd >= $association->odd
            ) {
                $data = $this->updateSimpleCase($association);
                if (strtolower($autounitDailySchedule->odd->predictionId) == strtolower($association->predictionId)) {
                    $autounitDailySchedule->odd->odd = $association->odd;
                    $autounitDailySchedule->odd->update();
                } else {
                    $newOdd = Odd::where("predictionId", "=", $association->predictionId)
                        ->where("matchId", "=", $this->event->match->id)
                        ->where("leagueId", "=", $this->event->match->leagueId)
                        ->first();

                    if ($newOdd) {
                        $newOdd->odd = $association->odd;
                        $newOdd->update();
                    } else {
                        $newOdd = Odd::create([
                            "matchId" => $this->event->match->id,
                            "leagueId" => $this->event->match->leagueId,
                            "predictionId" => $association->predictionId,
                            "odd" => $association->odd
                        ]);
                    }
                    $autounitDailySchedule->odd_id = $newOdd->id;
                    $autounitDailySchedule->update();
                }
            } else {
                self::where("siteId", "=", $this->siteId)
                    ->where("associationId", "=", $this->associationId)
                    ->delete();
                $autounit = new AutoUnitAddEvents();
                $autounit->fire($this->event->match, true, $autounitDailySchedule->id, false, true);
                $data = [
                    "site" => $this->site,
                    "status" => "Changed",
                    "type" => ucfirst($this->provider)
                ];
            }
        } else {
            $data = $this->updateSimpleCase($association);
        }
        return $data;
    }

    private function updateAutounitPublishedCase($association)
    {
        $data = [];

        $autounitDailySchedule = DailySchedule::where("siteId", "=", $this->siteId)
            ->where("tableIdentifier", "=", $this->tableIdentifier)
            ->where("tipIdentifier", "=", $this->tipIdentifier)
            ->where("match_id", "=", $this->event->match->primaryId)
            ->where("systemDate", "=", $this->systemDate)
            ->first();

        $autounitConfiguration = MonthlySetting::where("siteId", "=", $this->siteId)
            ->where("tableIdentifier", "=", $this->tableIdentifier)
            ->where("tipIdentifier", "=", $this->tipIdentifier)
            ->whereRaw("date = DATE_FORMAT('" . $this->systemDate . "', '%Y-%m')")
            ->first();

        if ($autounitDailySchedule) {
            if (
                strtolower($autounitDailySchedule->predictionGroup) == strtolower($association->prediction->group) &&
                $autounitConfiguration->minOdd <= $association->odd &&
                $autounitConfiguration->maxOdd >= $association->odd
            ) {
                $data = $this->updateSimplePublishedCase($association);

                if (strtolower($autounitDailySchedule->odd->predictionId) == strtolower($association->predictionId)) {
                    $autounitDailySchedule->odd->odd = $association->odd;
                    $autounitDailySchedule->odd->update();
                } else {
                    $newOdd = Odd::where("predictionId", "=", $association->predictionId)
                        ->where("matchId", "=", $this->event->match->id)
                        ->where("leagueId", "=", $this->event->match->leagueId)
                        ->first();

                    if ($newOdd) {
                        $newOdd->odd = $association->odd;
                        $newOdd->update();
                    } else {
                        $newOdd = Odd::create([
                            "matchId" => $this->event->match->id,
                            "leagueId" => $this->event->match->leagueId,
                            "predictionId" => $association->predictionId,
                            "odd" => $association->odd
                        ]);
                    }
                    $autounitDailySchedule->odd_id = $newOdd->id;
                    $autounitDailySchedule->update();
                }
            } else {
                if ($this->archiveHome) {
                    $this->archiveHome()->update([
                        "isVisible" => 0
                    ]);
                }
                if ($this->archiveBig) {
                    $this->archiveBig()->update([
                        "isVisible" => 0
                    ]); 
                }

                self::where("siteId", "=", $this->siteId)
                    ->where("associationId", "=", $this->associationId)
                    ->delete();
                $autounit = new AutoUnitAddEvents();
                $autounit->fire($this->event->match, true, $autounitDailySchedule->id, false, true);
                $data = [
                    "site" => $this->site,
                    "status" => "Changed",
                    "type" => ucfirst($this->provider)
                ];
            }
        } else {
            $data = $this->updateSimplePublishedCase($association);
        }
        return $data;
    }

    public function removePublishedDistribution()
    {
        if ($this->archiveHome) {
            $this->archiveHome()->update([
                "isVisible" => 0
            ]);
        }
        if ($this->archiveBig) {
            $this->archiveBig()->update([
                "isVisible" => 0
            ]); 
        }

        self::where("siteId", "=", $this->siteId)
            ->where("associationId", "=", $this->associationId)
            ->delete();

        $data = [
            "site" => $this->site,
            "status" => "Removed",
            "type" => ucfirst($this->provider)
        ];
        return $data;
    }

    public function changePublishedAutounit()
    {
        $data = null;
        $autounitDailySchedule = DailySchedule::where("siteId", "=", $this->siteId)
            ->where("tableIdentifier", "=", $this->tableIdentifier)
            ->where("tipIdentifier", "=", $this->tipIdentifier)
            ->where("match_id", "=", $this->event->match->primaryId)
            ->where("systemDate", "=", $this->systemDate)
            ->first();

        self::where("siteId", "=", $this->siteId)
            ->where("associationId", "=", $this->associationId)
            ->delete();
        if ($autounitDailySchedule) {
            if ($this->archiveHome) {
                $this->archiveHome()->update([
                    "isVisible" => 0
                ]);
            }
            if ($this->archiveBig) {
                $this->archiveBig()->update([
                    "isVisible" => 0
                ]); 
            }
    
            $autounit = new AutoUnitAddEvents();
            $autounit->fire($this->event->match, true, $autounitDailySchedule->id, false, true);
            $data = [
                "site" => $this->site,
                "status" => "Changed",
                "type" => ucfirst($this->provider)
            ];
        }
        return $data;
    }
}
