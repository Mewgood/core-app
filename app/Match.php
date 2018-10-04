<?php namespace App;

use Illuminate\Database\Eloquent\Model;

class Match extends Model {

    protected $table = 'match';

    protected $fillable = [
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
    ];

    public static function getLeagueMatches(array $leagueIds, string $date)
    {
        $matches = Match::whereIn("leagueId", $leagueIds)
                    ->whereRaw("DATE_FORMAT(eventDate, '%Y-%m-%d') = '" . $date . "'")
                    ->get()
                    ->toArray();
        return $matches;
    }
}
