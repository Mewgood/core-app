<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Log extends Model {

    protected $table = 'log';

    protected $fillable = [
        'identifier',
        'type',
        'module',
        'status',
        'info',
    ];

//    protected $hidden = [ ‘password’ ];
}




