<?php namespace App\Models\ImportFeed;

use Illuminate\Database\Eloquent\Model;

class ImportCountries extends Model {

	public $timestamps = false;
	
    protected $table = 'import_countries';

    protected $fillable = [
        'name',
        'country_code',
    ];

}

