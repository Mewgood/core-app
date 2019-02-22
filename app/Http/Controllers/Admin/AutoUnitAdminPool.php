<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AutoUnit\AdminPool;
use App\Models\AutoUnit\AdminPoolMatch;
use Carbon\Carbon;

class AutoUnitAdminPool extends Controller
{
    public function store(Request $request)
    {
        $pool = AdminPool::updateOrCreate(["pool_date" => $request->date]);

        // create a mapped array containing the pool_id
        $matches = AdminPool::getMatches($request->matches, $request->date, $pool);
        // link the matches to the pool
        // if the match is already linked it will skip it to prevent duplications
        foreach ($matches as $match) {
            AdminPoolMatch::updateOrCreate(["pool_id" => $match["pool_id"], "match_id" => $match["match_id"]], $match);
        }
        
        return response($matches, 200);
    }
    
    public function get(string $date, Request $request)
    {
        $data = AdminPool::getPoolMatches($date, $request->limit, $request->offset, $request->search["value"]);
        $pool["data"] = $data[0];
        $pool["recordsFiltered"] = $pool["recordsTotal"] = $data[1]; // total_count
        return response($pool, 200);
    }
    
    public function removeAdminPoolMatches(Request $request)
    {
        $matches = AdminPoolMatch::destroy($request->matches);
        return response($matches, 200);
    }
    
    public function getNotification()
    {
        $today = gmdate("Y-m-d");
        $adminPool = AdminPool::where("pool_date", "=", $today)->count();
        return response($adminPool, 200);
    }
}
