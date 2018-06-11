<?php namespace App\Models\ImportFeed;

use Illuminate\Database\Eloquent\Model;

class ImportTeams extends Model {

	public $timestamps = false;
	
    protected $table = 'import_teams';

    protected $fillable = [
        'team_id',
        'title',
    ];

}

