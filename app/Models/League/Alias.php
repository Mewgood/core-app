<?php namespace App\Models\League;

use Illuminate\Database\Eloquent\Model;

class Alias extends Model {

    protected $table = 'league_alias';

    protected $fillable = [
        'leagueId',
        'alias',
    ];

//    protected $hidden = [ ‘password’ ];
}
