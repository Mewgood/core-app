<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\AutoUnit\DailySchedule;

class AutoUnitDailySchedule extends Controller
{
    public function updateStatus(Request $request)
    {
        $autoUnitDailySchedule = DailySchedule::where("id", $request->schedule)->update(["statusId" => $request->statusId]);
        return response($autoUnitDailySchedule, 200);
    }
}
