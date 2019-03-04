<?php namespace App\Models\AutoUnit;

use Illuminate\Database\Eloquent\Model;

class League extends Model {

    protected $table = 'auto_unit_league';

    protected $fillable = [
        'type',
        'date',
        'siteId',
        'tipIdentifier',
        'leagueId',
    ];

    public static function getDefaultConfigurationLeagues($siteId)
    {
        $data = League::select("auto_unit_league.leagueId")
            ->where("auto_unit_league.siteId", "=", $siteId)
            ->where("type", "=", "default")
            ->get()
            ->toArray();
        return $data;
    }
}
