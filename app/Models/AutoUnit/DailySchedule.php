<?php namespace App\Models\AutoUnit;

use Illuminate\Database\Eloquent\Model;

class DailySchedule extends Model {

    protected $table = 'auto_unit_daily_schedule';

    protected $fillable = [
        'siteId',
        'date',
        'tipIdentifier',
        'tableIdentifier',
        'predictionGroup',
        'statusId',
        'status',
        'info',
        'systemDate',
        'invalid_matches',
        'match_id',
        'odd_id',
        'to_distribute'
    ];

//    protected $hidden = [ ‘password’ ];
}


