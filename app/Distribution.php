<?php namespace App;

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
            $this->delete();
            $data = [
                "site" => $this->site,
                "status" => "Removed",
                "type" => ucfirst($this->provider)
            ];
        }
        return $data;
    }

    public function updatePublishedDistribution($association)
    {
        $data = [];
        $packagePredictions = $this->package->packagePredictions()->get()->toArray();
        // check if the new prediction is in the available predictions of the site package
        if (array_search($association->predictionId, array_column($packagePredictions, 'predictionIdentifier')) !== false) {
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
                $this->delete();

                $data = [
                    "site" => $this->site,
                    "status" => "Removed",
                    "type" => ucfirst($this->provider)
                ];
            } else {

            }
        }
        return $data;
    }

    private function updateSimpleCase($association)
    {
        $this->odd = $association->odd;
        $this->predictionId = $association->predictionId;
        $this->predictionName = $this->prediction->name;
        $this->update();

        foreach ($this->events as $event) {
            $event->odd = $association->odd;
            $event->predictionId = $association->predictionId;
            $event->update();
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
        $this->odd = $association->odd;
        $this->predictionId = $association->predictionId;
        $this->predictionName = $this->prediction->name;
        $this->update();

        if ($this->archiveHome) {
            $this->archiveHome()->update([
                "odd" => $this->odd,
                "predictionId" => $this->predictionId,
                "predictionName" => $this->predictionName
            ]);
        }

        if ($this->archiveBig) {
            $this->archiveBig()->update([
                "odd" => $this->odd,
                "predictionId" => $this->predictionId,
                "predictionName" => $this->predictionName
            ]); 
        }

        foreach ($this->events as $event) {
            $event->odd = $association->odd;
            $event->predictionId = $association->predictionId;
            $event->update();
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

        if (
            strtolower($autounitDailySchedule->predictionGroup) == strtolower($this->prediction->group) &&
            strtolower($autounitDailySchedule->odd->predictionId) == strtolower($this->predictionId) &&
            $autounitConfiguration->minOdd <= $association->odd &&
            $autounitConfiguration->maxOdd >= $association->odd
        ) {
            $data = $this->updateSimplePublishedCase($association);
            $autounitDailySchedule->odd->odd = $association->odd;
            $autounitDailySchedule->odd->update();
        } else {
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

        if (
            strtolower($autounitDailySchedule->predictionGroup) == strtolower($this->prediction->group) &&
            strtolower($autounitDailySchedule->odd->predictionId) == strtolower($this->predictionId) &&
            $autounitConfiguration->minOdd <= $association->odd &&
            $autounitConfiguration->maxOdd >= $association->odd
        ) {
            $data = $this->updateSimplePublishedCase($association);
            $autounitDailySchedule->odd->odd = $association->odd;
            $autounitDailySchedule->odd->update();
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

        $this->delete();

        $data = [
            "site" => $this->site,
            "status" => "Removed",
            "type" => ucfirst($this->provider)
        ];
        return $data;
    }

    public function changePublishedAutounit()
    {
        $autounitDailySchedule = DailySchedule::where("siteId", "=", $this->siteId)
            ->where("tableIdentifier", "=", $this->tableIdentifier)
            ->where("tipIdentifier", "=", $this->tipIdentifier)
            ->where("match_id", "=", $this->event->match->primaryId)
            ->where("systemDate", "=", $this->systemDate)
            ->first();

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
        return $data;
    }
}
