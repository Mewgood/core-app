<?php 

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use App\Models\AutoUnit\DailySchedule;
use App\Match;
use App\Distribution;
use App\Event;

class ResetAutounit extends CronCommand
{
    protected $name = 'autounit:reset';
    protected $signature = 'autounit:reset {--site=} {--table=}';


    public function fire()
    {
        $scheduledEvents = $this->getAutoUnitSchedules();
        $this->resetMatchCounters($scheduledEvents);
        $this->resetScheduledEvents();
    }
    
    private function getAutoUnitSchedules()
    {
        $today = gmdate("Y-m-d");
        $data = DailySchedule::select(
                "match_id",
                "siteId",
                "tableIdentifier"
            )
            ->when($this->option("site"), function($query) {
                $query->where("auto_unit_daily_schedule.siteId", "=", $this->option("site"));
            })
            ->when($this->option("table"), function($query) {
                $query->where("auto_unit_daily_schedule.tableIdentifier", "=", $this->option("table"));
            })
            ->whereNotNull("match_id")
            ->where("systemDate", "=", $today)
            ->where("to_distribute", "=", 0)
            ->where("status", "!=", "success")
            ->get();
        return $data;
    }
    
    private function resetMatchCounters($scheduledEventsMatches)
    {
        $today = gmdate("Y-m-d");
        foreach($scheduledEventsMatches as $scheduledEventsMatch) {
            $event = Event::select(
                    "event.id"
                )
                ->join("distribution", "distribution.eventId", "event.id")
                ->join("match", "match.id", "event.matchId")
                ->where("match.primaryId", "=", $scheduledEventsMatch->match_id)
                ->where("distribution.siteid", "=", $scheduledEventsMatch->siteId)
                ->where("distribution.tableIdentifier", "=", $scheduledEventsMatch->tableIdentifier)
                ->first();

            if ($event) {
                Distribution::where("eventId", "=", $event->id)
                    ->where("siteId", "=", $scheduledEventsMatch->siteId)
                    ->where("tableIdentifier", "=", $scheduledEventsMatch->tableIdentifier)
                    ->delete();
                $distributionEventCounter = Distribution::where("eventId", "=", $event->id)->count();
                if ($distributionEventCounter == 0) {
                    $event->delete();
                }
            }
            
            Match::where("primaryId", $scheduledEventsMatch->match_id)
                ->update([
                    "sites_distributed_counter" => DB::raw('sites_distributed_counter - 1'),
                    "prediction_results" => "[]"
                ]);
        }
    }
    
    private function resetScheduledEvents()
    {
        $today = gmdate("Y-m-d");
        DailySchedule::where("systemDate", "=", $today)
            ->where("status", "!=", "success")
            ->when($this->option("site"), function($query) {
                $query->where("auto_unit_daily_schedule.siteId", "=", $this->option("site"));
            })
            ->when($this->option("table"), function($query) {
                $query->where("auto_unit_daily_schedule.tableIdentifier", "=", $this->option("table"));
            })
            ->update([
                "status" => "waiting",
                "info" => "[]",
                "match_id" => NULL,
                "to_distribute" => 0,
                "invalid_matches" => "[]",
                "is_from_admin_pool" => 0,
                "odd_id" => NULL
            ]);
    }
}