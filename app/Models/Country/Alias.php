<?php namespace App\Models\Country;

use Illuminate\Database\Eloquent\Model;

class Alias extends Model {

    protected $table = 'country_alias';

    protected $fillable = [
        'countryCode',
        'alias',
    ];

//    protected $hidden = [ ‘password’ ];
}


