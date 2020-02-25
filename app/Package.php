<?php namespace App;

use Illuminate\Database\Eloquent\Model;

class Package extends Model {

    protected $table = 'package';

    protected $fillable = [
        'id',
        'siteId',
        'name',
        'identifier',
        'tipIdentifier',
        'tableIdentifier',
        'paymentName',
        'vipFlag',
        'isVip',
        'isRecurring',
        'subscriptionType',
        'tipsPerDay',
        'subscription',
        'aliasTipsPerDay',
        'aliasSubscriptionType',
        'oldPrice',
        'discount',
        'price',
        'paymentCodePaypal',
        'paymentCodeHipay',
        'fromName',
        'subject',
        'template',
        'paused_autounit',
        'manual_pause'
    ];

//    protected $hidden = [ ‘password’ ];

    public function packagePredictions()
    {
        return $this->hasMany('App\PackagePrediction', 'packageId');
    }

    public function prediction()
    {
        return $this->hasOne('App\Prediction', 'predictionIdentifier', 'identifier');
    }
}
