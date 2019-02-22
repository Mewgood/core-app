<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Package;

class SubscriptionAlert extends Model {

    protected $table = 'subscription_alerts';

    protected $fillable = [
        'id',
        'package_id'
    ];
    
    public function store(Package $package)
    {
        $this->package_id = $package->id;
        $this->save();
    }
    
    public static function clearAlerts($tableIdentifier, $tipIdentifier, $siteId)
    {
        $packages = Package::select("package.id")
            ->where("package.tableIdentifier", "=", $tableIdentifier)
            ->where("package.tipIdentifier", "=", $tipIdentifier)
            ->where("package.siteId", "=", $siteId)
            ->get();
        SubscriptionAlert::whereIn("package_id", $packages)
            ->delete();
    }
}
