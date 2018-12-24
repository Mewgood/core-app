<?php namespace App\Models\AutoUnit;

use Illuminate\Database\Eloquent\Model;

class DefaultSetting extends Model {

    protected $table = 'auto_unit_default_setting';
    
    protected $appends = ["packages"];

    protected $fillable = [
        'siteId',
        'tipIdentifier',
        'tableIdentifier',
        'minOdd',
        'maxOdd',
        'draw',
        'prediction1x2',
        'predictionOU',
        'predictionAH',
        'predictionGG',
        'configType',
        'minWinrate',
        'maxWinrate',
        'minTips',
        'maxTips',
        'tipsPerDay',
    ];

    public function getPackagesAttribute()
    {
        return $this->attributes['packages'];
    }

    public function setPackagesAttribute($value)
    {
        $this->attributes['packages'] = $value;
    }

//    protected $hidden = [ ‘password’ ];
}

