<?php 

namespace App\Models\AutoUnit;

use App\Match;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AdminPool extends Model {

    protected $table = 'auto_unit_admin_pools';

    protected $fillable = [
        'country',
        'pool_date'
    ];
    
    protected $casts = [
        'pool_date' => 'date:Y-m-d',
    ];
    
    public static function getMatches(array $matchIds, string $date, AdminPool $pool)
    {
        $matches = Match::select(
                        DB::raw("$pool->id AS pool_id"),
                        "primaryId AS match_id"
                    )
                    ->whereIn("primaryId", $matchIds)
                    ->whereRaw("DATE_FORMAT(eventDate, '%Y-%m-%d') = '" . $date . "'")
                    ->get()
                    ->toArray();
        return $matches;
    }
    
    public static function getPoolMatches(string $date)
    {
        $matches = AdminPool::join("auto_unit_admin_pool_matches", "auto_unit_admin_pool_matches.pool_id", "auto_unit_admin_pools.id")
            ->join("match", "match.primaryId", "auto_unit_admin_pool_matches.match_id")
            ->where("pool_date", "=", $date)
            ->get();
        return $matches;
    }
}


