<?php namespace App;

use Illuminate\Database\Eloquent\Model;

class Association extends Model {

    protected $table = 'association';

    protected $fillable = [
        'eventId',
        'source',
        'provider',
        'type',
        'isNotip',
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
        'result',
        'statusId',
        'eventDate',
        'systemDate',
        'to_distribute'
    ];

    // get the status name associated with event
    public function status()
    {
        return $this->hasOne('App\AppResultStatus', 'id', 'statusId');
    }
//    protected $hidden = [ ‘password’ ];
}
