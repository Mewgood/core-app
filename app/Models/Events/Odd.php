<?php namespace App\Models\Events;

use Illuminate\Database\Eloquent\Model;

class Odd extends Model {

    protected $table = 'odd';

    protected $fillable = [
        'matchId',
        'leagueId',
        'predictionId',
        'odd',
        'initial_odd'
    ];

//    protected $hidden = [ ‘password’ ];
}



