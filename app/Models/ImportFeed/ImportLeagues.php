<?php namespace App\Models\ImportFeed;

use Illuminate\Database\Eloquent\Model;

class ImportLeagues extends Model {

	public $timestamps = false;
	
    protected $table = 'import_leagues';

    protected $fillable = [
        'league_id',
        'title',
        'country_code',
    ];

}

