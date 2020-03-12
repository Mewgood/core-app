<?php namespace App;

use App\Event;
use App\Models\Odd;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class Match extends Model {

    protected $table = 'match';

    protected $fillable = [
        'primaryId',
        'id',
        'country',
        'countryCode',
        'league',
        'leagueId',
        'homeTeam',
        'homeTeamId',
        'awayTeam',
        'awayTeamId',
        'result',
        'eventDate',
        'estimated_finished_time',
        'is_postponed'
    ];

    public function odds()
    {
        return $this->hasMany(Odd::class, "matchId");
    }

    public function events()
    {
        return $this->hasMany(Event::class, "matchId");
    }

    public function getLeagueMatches(array $leagueIds, string $date, int $limit, $offset, $search = NULL)
    {
        $matches = Match::select(
                        DB::raw('SQL_CALC_FOUND_ROWS *')
                    )
                    ->whereIn("leagueId", $leagueIds)
                    ->whereRaw("DATE_FORMAT(eventDate, '%Y-%m-%d') = '" . $date . "'")
                    ->where(function ($query) use ($date, $search) {
                        $query->when($search, function($innerQuery, $search) {
                            foreach ($this->fillable as $column) {
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
    
    public static function getMatchPredictionOdd($predictionIdentifier, $matchId)
    {
        $odd = Odd::select(
                    "odd",
                    "initial_odd"
                )
                ->where("predictionId", "=", $predictionIdentifier)
                ->where("matchId", "=", $matchId)
                ->first();
        return $odd;
    }
}
