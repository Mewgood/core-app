<?php namespace App;

use Illuminate\Database\Eloquent\Model;

class Event extends Model {

    protected $table = 'event';

    protected $fillable = [
        'matchId',
        'source',
        'provider',
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
        'result',
        'statusId',
        'eventDate',
    ];

    // get the status name associated with event
    public function status()
    {
        return $this->hasOne('App\AppResultStatus', 'id', 'statusId');
    }

    public function match()
    {
        return $this->hasOne('App\Match', 'id', 'matchId');
    }

//    protected $hidden = [ ‘password’ ];
}
