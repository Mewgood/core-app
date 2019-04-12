<?php 

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use App\Models\AutoUnit\DailySchedule;
use App\Match;
use App\Distribution;
use App\Event;
use App\Association;

class ResetAutounit extends CronCommand
{
    protected $name = 'autounit:reset';
    protected $signature = 'autounit:reset {--site=} {--table=}';


    public function fire()
    {
        $scheduledEvents = $this->getAutoUnitSchedules();
        $this->resetScheduledEvents();
        $this->resetMatchCounters($scheduledEvents);
    }
    
    private function getAutoUnitSchedules()
    {
        $today = gmdate("Y-m-d");
        $data = DailySchedule::select(
                "auto_unit_daily_schedule.match_id",
                "auto_unit_daily_schedule.siteId",
                "auto_unit_daily_schedule.tableIdentifier"
            )
            ->join("match", "match.primaryId", "auto_unit_daily_schedule.match_id")
            ->join("event", "event.matchId", "match.id")
            ->join("distribution", "distribution.eventId", "event.id")
            ->when($this->option("site"), function($query) {
                $query->where("auto_unit_daily_schedule.siteId", "=", $this->option("site"));
            })
            ->when($this->option("table"), function($query) {
                $query->where("auto_unit_daily_schedule.tableIdentifier", "=", $this->option("table"));
            })
            ->whereNotNull("auto_unit_daily_schedule.match_id")
            ->where("auto_unit_daily_schedule.systemDate", "=", $today)
            ->where("distribution.isPublish", "=", 0)
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
                    Association::where("eventId", "=", $event->id)->delete();
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
        DB::table('auto_unit_daily_schedule')->join("match", "match.primaryId", "auto_unit_daily_schedule.match_id")
            ->join("event", "event.matchId", "match.id")
            ->join("distribution", "distribution.eventId", "event.id")
            ->where("auto_unit_daily_schedule.systemDate", "=", $today)
            ->where("distribution.isPublish", "=", 0)
            ->when($this->option("site"), function($query) {
                $query->where("auto_unit_daily_schedule.siteId", "=", $this->option("site"));
            })
            ->when($this->option("table"), function($query) {
                $query->where("auto_unit_daily_schedule.tableIdentifier", "=", $this->option("table"));
            })
            ->update([
                "auto_unit_daily_schedule.status" => "waiting",
                "auto_unit_daily_schedule.info" => "[]",
                "auto_unit_daily_schedule.match_id" => NULL,
                "auto_unit_daily_schedule.to_distribute" => 0,
                "auto_unit_daily_schedule.invalid_matches" => "[]",
                "auto_unit_daily_schedule.is_from_admin_pool" => 0,
                "auto_unit_daily_schedule.odd_id" => NULL
            ]);
    }
}