<?php namespace App\Models\AutoUnit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
        $data = DailySchedule::select(
            DB::raw("
                SUM(
                    CASE
                        WHEN auto_unit_daily_schedule.statusId = 1 THEN 1 ELSE 0
                    END
                ) AS win"
            ),
            DB::raw("
                SUM(
                    CASE
                        WHEN auto_unit_daily_schedule.statusId = 2 THEN 1 ELSE 0
                    END
                ) AS loss"
            ),
            DB::raw("
                SUM(
                    CASE
                        WHEN auto_unit_daily_schedule.statusId = 3 THEN 1 ELSE 0
                    END
                ) AS draw"
            ),
            DB::raw("
                SUM(
                    CASE
                        WHEN package.isVip = 1 THEN 1 ELSE 0
                    END
                ) AS vip"
            ),
            "auto_unit_daily_schedule.date"
        )
        ->join("package", function ($query) {
            $query->on("package.siteId", "=", "auto_unit_daily_schedule.siteId");
            $query->on("package.tipIdentifier", "=", "auto_unit_daily_schedule.tipIdentifier");
            $query->on("package.tableIdentifier", "=", "auto_unit_daily_schedule.tableIdentifier");
        })
        ->whereRaw("auto_unit_daily_schedule.systemDate >= DATE_ADD(CURDATE(), INTERVAL -5 MONTH)")
        ->whereRaw('DATE_FORMAT(auto_unit_daily_schedule.systemDate, "%Y-%m") < DATE_FORMAT(CURDATE(), "%Y-%m")')
        ->where("auto_unit_daily_schedule.siteid" , "=", $siteId)
        ->groupBy(DB::raw('DATE_FORMAT(auto_unit_daily_schedule.systemDate, "%Y-%m")'))
        ->get();

        foreach ($data as $item) {
            $item->winRate = $item->win > 0 || $item->loss > 0 
                ? round(($item->win * 100) / ($item->win + $item->loss), 2) 
                : 0;
        }
        return $data;
    }
/*
    public static function getAutoUnitSiteStatistics()
    {
        $data = Site::select(
                "site.id",
                "site.name",
                "auto_unit_monthly_setting.date",
                DB::raw("
                    (
                        SELECT COUNT(DISTINCT pk.tableIdentifier)
                        FROM package pk
                        WHERE pk.siteId = site.id
                    ) AS siteTablesCounter"
                ),
                DB::raw("
                    (
                        SELECT COUNT(DISTINCT aums.tableIdentifier)
                        FROM auto_unit_monthly_setting aums
                        WHERE aums.siteId = site.id
                        AND DATE_FORMAT(STR_TO_DATE(aums.date, '%Y-%m'), '%Y-%m') = DATE_FORMAT(STR_TO_DATE(auto_unit_monthly_setting.date, '%Y-%m'), '%Y-%m')
                    ) AS siteConfiguredTablesCounter"
                ),
                DB::raw("
                    (CASE
                        WHEN (SELECT siteTablesCounter) = (SELECT siteConfiguredTablesCounter) THEN 1
                        WHEN (SELECT siteConfiguredTablesCounter) = 0 THEN NULL
                        ELSE 2
                    END) AS configurationStatus"
                )
            )
            ->join("package", "package.siteId", "=", "site.id")
            ->leftJoin("auto_unit_monthly_setting", function ($query) {
                $query->on("auto_unit_monthly_setting.siteId", "=", "site.id");
                $query->whereRaw('DATE_FORMAT(STR_TO_DATE(auto_unit_monthly_setting.date, "%Y-%m"), "%Y-%m") >= DATE_FORMAT(CURDATE(), "%Y-%m")');
            })
            ->groupBy("date", "site.id")
            ->get()
            ->toArray();
        $data = self::formatAutoUnitSiteStatisticsData($data);
        return $data;
    }
*/
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
                )
            )
            ->join("package", "package.siteId", "=", "site.id")
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
                    $hasSubscription = Site::hasSubscription($item["id"]); // RU || NU
                    $configurationStatus = false;
                    $temp = [
                        "date"  => $tempDate,
                        "id"    => $item["id"],
                        "siteId" => $item["siteId"],
                        "name"  => $item["name"],
                        "pause" => $item["paused_autounit"],
                        "display" => $firstIteration,
                        "tipIdentifier" => $item["tipIdentifier"],
                        "isVip" => $item["isVip"]
                    ];
                    if ($item["date"] == "") {
                        $item["date"] = $tempDate;
                    }
                    if ($hasSubscription) {
                        $formatedData["ru"][$item["id"]]["display"] = $firstIteration;
                        if (
                            isset($formatedData["ru"][$item["id"]][$tempDate]["configurationStatus"]) &&
                            $formatedData["ru"][$item["id"]][$tempDate]["configurationStatus"] !== false
                        ) {
                            $configurationStatus = $formatedData["ru"][$item["id"]][$tempDate]["configurationStatus"];
                        } else if ($item["date"] == $tempDate) {
                            $configurationStatus = $item["configurationStatus"];
                        }
                        $temp["configurationStatus"] = $configurationStatus;
                        $formatedData["ru"][$item["id"]][$tempDate] = $temp;
                    } else {
                        $formatedData["nu"][$item["id"]]["display"] = $firstIteration;
                        if (
                            isset($formatedData["nu"][$item["id"]][$tempDate]["configurationStatus"]) &&
                            $formatedData["nu"][$item["id"]][$tempDate]["configurationStatus"] !== false
                        ) {
                            $configurationStatus = $formatedData["nu"][$item["id"]][$tempDate]["configurationStatus"];
                        } else if ($item["date"] == $tempDate) {
                            $configurationStatus = $item["configurationStatus"];
                        }
                        $temp["configurationStatus"] = $configurationStatus;
                        $formatedData["nu"][$item["id"]][$tempDate] = $temp;
                    }
                }
                $firstIteration = false;
            }
            $item["tipsLeft"] = Site::getLastSubscription($item["id"])->tipsLeft;
            $item["lastSubscription"] = Site::getLastSubscription($item["id"])->dateEnd;

            // overwrite it if the autounit is set for that month
            if ($hasSubscription) {
                $formatedData["ru"][$item["id"]][$item["date"]]["lastSubscription"] = $item["lastSubscription"];
                $formatedData["ru"][$item["id"]][$item["date"]]["tipsLeft"] = $item["tipsLeft"];
            } else {
                $formatedData["nu"][$item["id"]][$item["date"]]["lastSubscription"] = $item["lastSubscription"];
                $formatedData["nu"][$item["id"]][$item["date"]]["tipsLeft"] = $item["tipsLeft"];
            }
        }
        return $formatedData;
    }
}


