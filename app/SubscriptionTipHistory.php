<?php namespace App;

use App\Subscription;
use Illuminate\Database\Eloquent\Model;

class SubscriptionTipHistory extends Model {

    protected $table = 'subscription_tip_history';

    protected $fillable = [
        'id',
        'subscriptionId',
        'customerId',
        'eventId',
        'siteId',
        'pocessSubscription',
        'processType',
        'isCustom',
        'type',
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
    ];
    
    public function subscription()
    {
        return $this->belongsTo(Subscription::class, "subscriptionId");
    }
}
