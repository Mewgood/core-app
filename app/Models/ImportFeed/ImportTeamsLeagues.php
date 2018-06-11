<?php namespace App\Models\ImportFeed;

use Illuminate\Database\Eloquent\Model;

class ImportTeamsLeagues extends Model {

	public $timestamps = false;
	
    protected $table = 'import_teams_leagues';

    protected $fillable = [
        'league_id',
        'team_id',
    ];

}

