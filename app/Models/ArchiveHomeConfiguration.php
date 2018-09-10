<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Site;

class ArchiveHomeConfiguration extends Model
{
    protected $table = 'archive_home_conf';
    
    public static function getSiteConfiguration($token)
    {
        $data = Site::select("*")
            ->join("archive_home_conf", "archive_home_conf.siteId", "site.id")
            ->where("site.token", $token)
            ->first();
        return $data;
    }
}