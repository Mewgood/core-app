<?php namespace App\Models\ImportFeed;

use Illuminate\Database\Eloquent\Model;

class ImportLeaguesCountries extends Model {

	public $timestamps = false;
	
    protected $table = 'import_leagues_countries';

    protected $fillable = [
        'league_id',
        'country_code',
    ];

}

