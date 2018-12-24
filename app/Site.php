<?php namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Site extends Model {

    protected $table = 'site';

    protected $fillable = [
        'name',
        'email',
        'url',
        'smtpHost',
        'smtpPort',
        'smtpUser',
        'smtpPassword',
        'smtpEncryption',
        'imapHost',
        'imapPort',
        'imapUser',
        'imapPassword',
        'imapEncryption',
        'dateFormat',
        'isConnect',
        'token',
    ];

    public static function getCMSSites() {
        $data = Site::select("*")
            ->where("type", "=", "cms")
            ->get();
        return $data;
    }
    
    public static function hasSubscription($site) 
    {
        $data = Site::join("subscription", "subscription.siteId", "=", "site.id")
            ->where("site.id", "=", $site)
            ->where(function ($query) {
                $query->whereRaw("DATE_FORMAT(subscription.dateEnd, '%Y-%m-%d') >= CURDATE()")
                    ->orWhere("subscription.tipsLeft", ">", 0);
            })
            ->exists();
        return $data;
    }
    
    public static function getLastSubscription($site)
    {
        $data = Site::select(
                DB::raw("MAX(subscription.dateEnd) AS dateEnd"),
                DB::raw("MAX(subscription.tipsLeft) AS tipsLeft")
            )
            ->join("subscription", "subscription.siteId", "=", "site.id")
            ->where("site.id", "=", $site)
            ->first();
        return $data;
    }
}
