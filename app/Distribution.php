<?php namespace App;

use Illuminate\Database\Eloquent\Model;

class Distribution extends Model {

    protected $table = 'distribution';

    protected $fillable = [
        'associationId',
        'eventId',
        'source',
        'provider',
        'siteId',
        'packageId',
        'tableIdentifier',
        'tipIdentifier',
        'isEmailSend',
        'isPublish',
        'isNoTip',
        'isVip',
        'country',
        'countryCode',
        'league',
        'leagueId',
        'homeTeam',
        'homeTeamId',
        'awayTeam',
        'awayTeamId',
        'odd',
        'predictionId',
        'predictionName',
        'result',
        'statusId',
        'eventDate',
        'systemDate',
        'mailingDate',
        'publishTime',
        'isPublished',
        'to_distribute'
    ];

    // get the status name of distributed event
    public function status()
    {
        return $this->hasOne('App\AppResultStatus', 'id', 'statusId');
    }

    public static function removeUnused()
    {
        $today = gmdate("Y-m-d");
        $from = strtotime($today . "-6 months"); //unix timestamp
        $from = gmdate("Y-m-d", $from); // Y-m-d string format

        Distribution::where("systemDate", "<", $from)->delete();
    }
}
