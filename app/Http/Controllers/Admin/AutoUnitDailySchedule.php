<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\AutoUnit\DailySchedule;

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
}
