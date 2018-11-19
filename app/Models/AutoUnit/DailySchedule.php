<?php namespace App\Models\AutoUnit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
        'to_distribute'
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
            $item->winRate = $item->win > 0 || $item->loss < 0 
                ? round(($item->win * 100) / ($item->win + $item->loss), 2) 
                : 0;
        }
        return $data;
    }
}


