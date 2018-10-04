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
        
        return response()->json($matches, 200);
    }
    
    public function get(string $date)
    {
        $pool = AdminPool::getPoolMatches($date);
        return response()->json($pool, 200);
    }
    
    public function removeAdminPoolMatches(Request $request)
    {
        $matches = AdminPoolMatch::destroy($request->ids);
        return response()->json($matches, 200);
    }
}
