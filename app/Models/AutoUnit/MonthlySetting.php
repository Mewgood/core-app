<?php namespace App\Models\AutoUnit;

use Illuminate\Database\Eloquent\Model;

class MonthlySetting extends Model {

    protected $table = 'auto_unit_monthly_setting';
    protected $appends = ["packages"];

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


