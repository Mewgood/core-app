<?php namespace App\Models\AutoUnit;

use Illuminate\Database\Eloquent\Model;

class MonthlySetting extends Model {

    protected $table = 'auto_unit_monthly_setting';

    protected $fillable = [
        'siteId',
        'date',
        'tipIdentifier',
        'tableIdentifier',
        'minOdd',
        'maxOdd',
        'win',
        'loss',
        'draw',
        'prediction1x2',
        'predictionOU',
        'predictionAH',
        'predictionGG',
        'winrate',
        'configType',
        'tipsPerDay',
        'tipsNumber',
    ];

//    protected $hidden = [ ‘password’ ];
}


