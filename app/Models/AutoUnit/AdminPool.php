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
    
    private static $searchableColumns = [
        'primaryId',
        'country',
        'countryCode',
        'league',
        'leagueId',
        'homeTeam',
        'homeTeamId',
        'awayTeam',
        'awayTeamId',
        'result',
        'eventDate'
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
    
    public static function getPoolMatches(string $date, int $limit, int $offset, string $search)
    {
        $matches = AdminPool::select(
                DB::raw('SQL_CALC_FOUND_ROWS auto_unit_admin_pool_matches.id'),
                "match.primaryId",
                "match.league",
                "match.homeTeam",
                "match.awayTeam",
                "match.result"
            )
            ->join("auto_unit_admin_pool_matches", "auto_unit_admin_pool_matches.pool_id", "auto_unit_admin_pools.id")
            ->join("match", "match.primaryId", "auto_unit_admin_pool_matches.match_id")
            ->where("pool_date", "=", $date)
            ->where(function ($query) use ($date, $search) {
                $query->when($search, function($innerQuery, $search) {
                    foreach (self::$searchableColumns as $column) {
                        $innerQuery->orWhere($column, "LIKE", "%$search%");
                    }
                    return $innerQuery;
                });
            })
            ->limit($limit)
            ->offset($offset)
            ->get();
        $total = DB::select(DB::raw('SELECT FOUND_ROWS() as total_count'));
        return [$matches, $total[0]->total_count];
    }
    
    public static function getAutoUnitPoolMatches(string $date)
    {
        $matches = AdminPool::select(
                "match.id",
                "match.primaryId",
                "match.league",
                "match.leagueId",
                "match.homeTeam",
                "match.homeTeamId",
                "match.awayTeam",
                "match.awayTeamId",
                "match.result",
                "match.countryCode",
                "match.country",
                "match.eventDate",
                "match.sites_distributed_counter"
            )
            ->join("auto_unit_admin_pool_matches", "auto_unit_admin_pool_matches.pool_id", "auto_unit_admin_pools.id")
            ->join("match", "match.primaryId", "auto_unit_admin_pool_matches.match_id")
            ->where("pool_date", "=", $date)
            ->get()
            ->toArray();

        return $matches;
    }
}


