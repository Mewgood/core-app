<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Site;

use App\Package;
use App\Models\AutoUnit\DailySchedule;
use App\Models\AutoUnit\DefaultSetting;

class AutoUnitDailySchedule extends Controller
{
    public function updateFields(Request $request)
    {
        $autoUnitDailySchedule = DailySchedule::where("id", $request->schedule["id"])->update($request->schedule);
        return response($autoUnitDailySchedule, 200);
    }
    
    public function getMonthlyStatistics(Request $request)
    {
        $statistics = DailySchedule::getMonthlyStatistics($request->siteId);
        return response($statistics, 200);
    }
    
    public function getAutoUnitSiteStatistics()
    {
        $statistics = DailySchedule::getAutoUnitSiteStatistics();
        return response($statistics, 200);
    }
    
    public function toggleState(Request $request)
    {
        Package::when($request->tipIdentifier, function($query, $tipIdentifier) {
                return $query->where("tipIdentifier", "=", $tipIdentifier);
            })
            ->when($request->site, function($query, $site) {
                return $query->where("siteId", "=", $site);
            })
            ->update(["paused_autounit" => !$request->state, "manual_pause" => $request->manual_pause]);

        $package = Package::when($request->tipIdentifier, function($query, $tipIdentifier) {
                return $query->where("tipIdentifier", "=", $tipIdentifier);
            })
            ->when($request->site, function($query, $site) {
                return $query->where("siteId", "=", $site);
            })
            ->first();
        if (!$request->tipIdentifier) {
            \App\Models\Config::where("name", "=", "autounit_all_state")->update(["value" => !$request->state]);
        }
        
        return response($package, 200);
    }
    
    public function saveMonthlyConfiguration(Request $request)
    {
        $response = null;
        if ($request->date == "default") {
            $response = DefaultSetting::updateSettings($request);
        } else {
            $response = DailySchedule::saveMonthlyConfiguration($request);
        }
        if (!$response) {
            return [
                'type' => 'error',
                'message' => 'Unknown configuration type',
            ];
        } else {
            return $response;
        }
    }
}
