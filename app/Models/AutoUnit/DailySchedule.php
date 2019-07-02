<?php 

namespace App\Models\AutoUnit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

use App\ArchiveBig;
use App\Site;

class DailySchedule extends Model {

    protected $table = 'auto_unit_daily_schedule';

    protected $fillable = [
        'siteId',
        'date',
        'tipIdentifier',
        'tableIdentifier',
        'predictionGroup',
        'statusId',
        'status',
        'info',
        'systemDate',
        'invalid_matches',
        'match_id',
        'odd_id',
        'to_distribute',
        'is_from_admin_pool'
    ];

    public static function getMonthlyStatistics(int $siteId)
    {
        $data = ArchiveBig::select(
            DB::raw("
                SUM(
                    CASE
                        WHEN archive_big.statusId = 1 THEN 1 ELSE 0
                    END
                ) AS win"
            ),
            DB::raw("
                SUM(
                    CASE
                        WHEN archive_big.statusId = 2 THEN 1 ELSE 0
                    END
                ) AS loss"
            ),
            DB::raw("
                SUM(
                    CASE
                        WHEN archive_big.statusId = 3 THEN 1 ELSE 0
                    END
                ) AS draw"
            ),
            DB::raw("
                SUM(
                    CASE
                        WHEN archive_big.isVip = 1 THEN 1 ELSE 0
                    END
                ) AS vip"
            ),
            DB::raw('DATE_FORMAT(archive_big.systemDate, "%Y-%m") AS date')
        )
        ->whereRaw("archive_big.systemDate >= DATE_ADD(CURDATE(), INTERVAL -5 MONTH)")
        ->whereRaw('DATE_FORMAT(archive_big.systemDate, "%Y-%m") < DATE_FORMAT(CURDATE(), "%Y-%m")')
        ->where("archive_big.siteId" , "=", $siteId)
        ->groupBy(DB::raw('DATE_FORMAT(archive_big.systemDate, "%Y-%m")'))
        ->get();

        foreach ($data as $item) {
            $item->winRate = $item->win > 0 || $item->loss > 0 
                ? round(($item->win * 100) / ($item->win + $item->loss), 2) 
                : 0;
        }
        return $data;
    }

    public static function getAutoUnitSiteStatistics()
    {
        $data = Site::select(
                "site.name",
                "site.id AS siteId",
                "auto_unit_monthly_setting.date",
                "package.tipIdentifier",
                "package.id",
                "package.isVip",
                "package.paused_autounit",
                DB::raw("
                    (
                        SELECT COUNT(aums.tipIdentifier)
                        FROM auto_unit_monthly_setting aums
                        WHERE aums.siteId = site.id
                        AND DATE_FORMAT(STR_TO_DATE(aums.date, '%Y-%m'), '%Y-%m') = DATE_FORMAT(STR_TO_DATE(auto_unit_monthly_setting.date, '%Y-%m'), '%Y-%m')
                        AND aums.tipIdentifier = package.tipIdentifier
                    ) AS siteConfiguredTablesCounter"
                ),
                DB::raw("
                    (CASE
                        WHEN (SELECT siteConfiguredTablesCounter) > 0 THEN 1
                        WHEN (SELECT siteConfiguredTablesCounter) = 0 THEN NULL
                        ELSE 2
                    END) AS configurationStatus"
                ),
                "subscription_alerts.package_id AS packageAlert"
            )
            ->join("package", "package.siteId", "=", "site.id")
            ->leftJoin("subscription_alerts", "subscription_alerts.package_id", "package.id")
            ->leftJoin("auto_unit_monthly_setting", function ($query) {
                $query->on("auto_unit_monthly_setting.siteId", "=", "site.id");
                $query->whereRaw('DATE_FORMAT(STR_TO_DATE(auto_unit_monthly_setting.date, "%Y-%m"), "%Y-%m") >= DATE_FORMAT(CURDATE(), "%Y-%m")');
            })
            ->groupBy("date", "site.id", "package.tipIdentifier")
            ->get()
            ->toArray();
        $data = self::formatAutoUnitSiteStatisticsData($data);
        return $data;
    }

    // populate with empty data for the missing dates
    private static function formatAutoUnitSiteStatisticsData($data)
    {
        $dates = [0, 1, 2, 3, 4, 5, 6];
        $formatedData = [];

        foreach ($data as $item) {
            // set the empty value for each month
            $firstIteration = true;
            foreach ($dates as $date) {
                $tempDate = gmdate('Y-m', strtotime('+ ' . $date . ' month'));
                if (!isset($formatedData[$item["id"]][$tempDate])) {
                    $hasSubscription = Site::hasSubscription($item["siteId"], $item["id"]); // RU || NU
                    $configurationStatus = false;
                    $temp = [
                        "date"  => $tempDate,
                        "id"    => $item["id"],
                        "siteId" => $item["siteId"],
                        "name"  => $item["name"],
                        "pause" => $item["paused_autounit"],
                        "display" => $firstIteration,
                        "tipIdentifier" => $item["tipIdentifier"],
                        "isVip" => $item["isVip"],
                    ];
                    if ($item["date"] == "") {
                        $item["date"] = $tempDate;
                    }
                    $type = "nu";
                    if ($hasSubscription) {
                        $type = "ru";
                        
                    }
                    $formatedData[$type][$item["id"]]["display"] = $firstIteration;
                    $formatedData[$type][$item["id"]]["packageAlert"] = $item["packageAlert"];
                    if (
                        isset($formatedData[$type][$item["id"]][$tempDate]["configurationStatus"]) &&
                        $formatedData[$type][$item["id"]][$tempDate]["configurationStatus"] !== false
                    ) {
                        $configurationStatus = $formatedData[$type][$item["id"]][$tempDate]["configurationStatus"];
                    } else if ($item["date"] == $tempDate) {
                        $configurationStatus = $item["configurationStatus"];
                    }
                    $temp["configurationStatus"] = $configurationStatus;
                    $formatedData[$type][$item["id"]][$tempDate] = $temp;
                }
                $firstIteration = false;
            }
            $item["tipsLeft"] = Site::getLastSubscription($item["siteId"])->tipsLeft;
            $item["lastSubscription"] = Site::getLastSubscription($item["siteId"])->dateEnd;

            // overwrite it if the autounit is set for that month
            $formatedData[$type][$item["id"]][date("Y-m", strtotime($item["date"]))]["lastSubscription"] = $item["lastSubscription"];
            $formatedData[$type][$item["id"]][date("Y-m", strtotime($item["date"]))]["tipsLeft"] = $item["tipsLeft"];
        }
        return $formatedData;
    }
    
    public static function saveMonthlyConfiguration($data)
    {
        $leagues = json_decode($data->leagues, true);
        if ($data->configType == 'tips') {
            if ($data->win + $data->loss + $data->draw != $data->tipsNumber)
                return [
                    'type' => 'error',
                    'message' => 'Win + Loss + Draw must be equal with TipsNumber',
                    'siteId' => $data->siteId,
                    'tip' => $data->tipIdentifier
                ];
        }

        if ($data->configType == 'days') {

            $dayInMonth = (int) date('t', strtotime($data->date . '-01'));
            $totalTips = $dayInMonth * $data->tipsPerDay;
            if ($data->win + $data->loss + $data->draw != $totalTips)
                return [
                    'type' => 'error',
                    'message' => 'Win + Loss + Draw must be equal with TipsPerDay * number of days in month (' . $totalTips . ')',
                    'siteId' => $data->siteId,
                    'tip' => $data->tipIdentifier
                ];
        }

        // create or update monthly settings
        $defaultExists = \App\Models\AutoUnit\MonthlySetting::where('siteId', $data->siteId)
            ->where('tipIdentifier', $data->tipIdentifier)
            ->where('date', $data->date)
            ->count();

        if (! $defaultExists) {
            $default = \App\Models\AutoUnit\MonthlySetting::create(is_a($data, "Illuminate\Http\Request") ? $data->all() : $data->attributes);
        } else {
            $default = \App\Models\AutoUnit\MonthlySetting::where('siteId', $data->siteId)
                ->where('tipIdentifier', $data->tipIdentifier)
                ->where('date', $data->date)
                ->first();

            $default->date = $data->date;
            $default->minOdd = $data->minOdd;
            $default->maxOdd = $data->maxOdd;
            $default->prediction1x2 = $data->prediction1x2;
            $default->predictionOU = $data->predictionOU;
            $default->predictionAH = $data->predictionAH;
            $default->predictionGG = $data->predictionGG;
            $default->win = $data->win;
            $default->loss = $data->loss;
            $default->draw = $data->draw;
            $default->winrate = $data->winrate;
            $default->configType = $data->configType;
            $default->tipsPerDay = $data->tipsPerDay;
            $default->tipsNumber = $data->tipsNumber;
            $default->save();
        }

        // delete all schedule for selected month
        \App\Models\AutoUnit\DailySchedule::where('siteId', $data->siteId)
            ->where('tipIdentifier', $data->tipIdentifier)
            ->where('date', $data->date)
            ->where('to_distribute', '=', 0)
            ->delete();

        // create monthly schedule
        $default->{"predictionO/U"} = $default->predictionOU; 
        $default->{"predictionAH"} = $default->predictionAH;
        $default->{"predictionG/G"} = $default->predictionGG;
        $scheduleInstance = new \App\Src\AutoUnit\Schedule($default);
        $scheduleInstance->createSchedule();

        foreach ($scheduleInstance->getSchedule() as $day) {
            \App\Models\AutoUnit\DailySchedule::create($day);
        }

        // save associated leagues
        \App\Models\AutoUnit\League::where('siteId', $data->siteId)
            ->where('tipIdentifier', $data->tipIdentifier)
            ->where('type', 'monthly')
            ->where('date', $data->date)
            ->delete();

        if (is_array($leagues) && count($leagues) > 0) {
            foreach ($leagues as $league) {
                \App\Models\AutoUnit\League::create([
                    'siteId' => $data->siteId,
                    'leagueId' => is_a($data, "Illuminate\Http\Request") ? $league : $league["leagueId"],
                    'tipIdentifier' => $data->tipIdentifier,
                    'type' => 'monthly',
                    'date' => $data->date,
                ]);
            }
        }
        return [
            'type' => 'success',
            'message' => '*** Monthly configuration was updated with success.',
            'siteId' => $data->siteId,
            'tip' => $data->tipIdentifier
        ];
    }
}


