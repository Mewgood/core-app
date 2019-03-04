<?php 

namespace App\Models\AutoUnit;

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

    public static function updateSettings($data)
    {
        $leagues = json_decode($data->leagues, true);
        $defaultExists = DefaultSetting::where('siteId', $data->siteId)
            ->where('tipIdentifier', $data->tipIdentifier)
            ->count();

        if (! $defaultExists) {
            $default = DefaultSetting::create($data->all());
        } else {
            $default = DefaultSetting::where('siteId', $data->siteId)
                ->where('tipIdentifier', $data->tipIdentifier)
                ->first();

            $default->minOdd = $data->minOdd;
            $default->maxOdd = $data->maxOdd;
            $default->prediction1x2 = $data->prediction1x2;
            $default->predictionOU = $data->predictionOU;
            $default->predictionAH = $data->predictionAH;
            $default->predictionGG = $data->predictionGG;
            $default->draw = $data->input('draw');
            $default->configType = $data->configType;
            $default->minWinrate = $data->minWinrate;
            $default->maxWinrate = $data->maxWinrate;
            $default->tipsPerDay = $data->tipsPerDay;
            $default->save();
        }

        // save associated leagues
        \App\Models\AutoUnit\League::where('siteId', $data->siteId)
            ->where('tipIdentifier', $data->tipIdentifier)
            ->where('type', 'default')
            ->delete();

        if (is_array($leagues) && count($leagues) > 0) {
            foreach ($leagues as $league) {
                \App\Models\AutoUnit\League::create([
                    'siteId' => $data->siteId,
                    'tipIdentifier' => $data->tipIdentifier,
                    'leagueId' => $league,
                    'type' => 'default',
                ]);
            }
        }

        return [
            'type' => 'success',
            'message' => '*** Default configuration was updated with success.'
        ];
    }
}

