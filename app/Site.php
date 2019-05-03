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
        'token'
    ];

    public static function getCMSSites() {
        $data = Site::select("*")
            ->where("type", "=", "cms")
            ->get();
        return $data;
    }
    
    public static function hasSubscription($site, $package) 
    {
        $data = Site::join("subscription", "subscription.siteId", "=", "site.id")
            ->where("site.id", "=", $site)
            ->where("subscription.packageId", "=", $package)
            ->where("status", "!=", "archived")
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
    
    public static function withDefaultConfigurations()
    {
        $data = Site::select(
            "auto_unit_default_setting.*"
        )
        ->join("auto_unit_default_setting", "auto_unit_default_setting.siteId", "site.id")
        ->where("site.generate_autounit_monthly", "=", 1)
        ->get();
        
        return $data;
    }
    
    public static function getSitesDistributions($date, $real_user_sort = 0, $vip_user_sort = 0, $emails_sort = 0, $no_tip_vip = 0)
    {
        $data = Site::select(
            "site.id AS siteId",
            "site.name AS siteName",
            "distribution.*",
            "distribution.id AS distributionId",
            "auto_unit_daily_schedule.is_from_admin_pool",
            "package.*",
            "package_section.section AS ruNu",
            DB::raw('(
                    SELECT COUNT(distribution.id) 
                    FROM distribution 
                    WHERE distribution.packageId = package.id 
                    AND distribution.systemDate = "' . $date . '"
                ) AS totalEvents'
            ),
            DB::raw('(
                    SELECT COUNT(distribution.id) 
                    FROM distribution 
                    WHERE distribution.packageId = package.id 
                    AND distribution.systemDate = "' . $date . '"
                    AND distribution.isPublish
                ) AS totalEventsPublished'
            ),
            DB::raw('(
                    SELECT COUNT(distribution.id) 
                    FROM distribution 
                    WHERE distribution.packageId = package.id 
                    AND distribution.systemDate = "' . $date . '"
                    AND distribution.isEmailSend
                ) AS totalEventsMailSent'
            ),
            DB::raw("IF ((SELECT(totalEvents)) = (SELECT(totalEventsMailSent)), 1, 0) AS emailsSent")
        )
        ->leftJoin("site_package", "site_package.siteId", "site.id")
        ->leftJoin("package", "package.id", "site_package.packageId")
        ->leftJoin("distribution", function($query) use($date) {
            $query->on("distribution.packageId", "package.id");
            $query->where("distribution.systemDate", $date);
        })
        ->leftJoin("event", "event.id", "distribution.eventId")
        ->leftJoin("match", "match.id", "event.matchId")
        ->leftJoin("package_section", function($query) use($date) {
            $query->on("package_section.packageId", "package.id");
            $query->where("package_section.systemDate", $date);
        })
        ->leftJoin("auto_unit_daily_schedule", function($query) {
            $query->on("auto_unit_daily_schedule.match_id", "match.primaryId");
            $query->on("auto_unit_daily_schedule.siteId", "distribution.siteId");
        })
        ->groupBy("package.siteId", "package.tipIdentifier", "distribution.eventId")
        ->when($real_user_sort, function ($query, $real_user_sort) {
            return $query->where('package_section.section', "=", $real_user_sort);
        })
        ->when($vip_user_sort == "notvip", function ($query) {
            return $query->where('package.isVip', "=", 0);
        })
        ->when($vip_user_sort == "vip", function ($query) {
            return $query->where('package.isVip', "=", 1);
        })
        ->when($emails_sort == "sent", function ($query) {
            return $query->where('isEmailSend', "=", 1);
        })
        ->when($emails_sort == "unsent", function ($query) {
            return $query->where('isEmailSend', "=", 0);
        })
        ->when($no_tip_vip, function ($query) {
            $query->where('distribution.isVip', "=", 1);
            $query->where("distribution.result", "!=", "");
            $query->orWhere("distribution.isVip", "=", 0);
        })
        ->orderBy("distribution.eventId", "DESC")
        ->get();
        return $data;
    }
}
