<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ArchiveHome extends Controller
{
    /*
     * @return array()
     */
    public function index(Request $r)
    {
        $data = [];

        $siteId = $r->input('siteId');
        $tableIdentifier = $r->input('tableIdentifier');

        // get events
        $data['events'] = \App\ArchiveHome::select(
                "*",
                DB::raw("DATE(eventDate) AS eventDate")
            )
            ->where('siteId', $siteId)
            ->where('tableIdentifier', $tableIdentifier)
            ->orderBy('order', 'ASC')
            ->orderBy('isVip', 'ASC')
            ->orderBy('eventDate', 'DESC')
            ->get()
            ->toArray();

        // get archive home config
        $data['conf'] = \App\ArchiveHomeConf::where('siteId', $siteId)
            ->where('tableIdentifier', $tableIdentifier)
            ->first();

        return $data;
    }

    // @param integer $id
    // @return event || null
    public function get($id) {
        return \App\ArchiveHome::find($id);
    }

    // @param $id,
    // toogle show/hide an event from archiveHome
    // @return array()
    public  function toogleShowHide($id)
    {
        $event = \App\ArchiveHome::find($id);

        if (!$event)
            return [
                'type' => 'error',
                'message' => "This event not exist anymore!",
            ];

        $event->isVisible ? $event->isVisible = '0' : $event->isVisible = '1';
        $event->save();

        return [
            'type' => 'success',
            'message' =>"Status of event was successfful changed!",
        ];
    }

    // @param array $order
    // This will save modified order for events in archive big
    // @return void
    public function setOrder(Request $r) {
        $order = $r->input('order');

        foreach ($order as $item) {
            $event = \App\ArchiveHome::find($item['id']);
            $event->order = $item['order'];
            $event->update();
        }

        return [
            'type' => 'success',
            'message' =>"Orser was successful changed.",
        ];
    }

    // @ param integer $siteId
    // @ param string $tableIdentifier
    // This will increment order for each event
    // @return void
    public function incrementOrder($siteId, $tableIdentifier) {
        $events = \App\ArchiveHome::where('siteId', $siteId)
            ->where('tableIdentifier', $tableIdentifier)
            ->get();

        foreach ($events as $event) {
            $event->order++;
            $event->update();
        }
    }

    public function saveConfiguration(Request $r) {
        $siteId = $r->input('siteId');
        $tableIdentifier = $r->input('tableIdentifier');
        $eventsNumber = $r->input('eventsNumber');
        $dateStart = $r->input('dateStart');

        $date = new \DateTime($dateStart);
        if (! $date || $date->format('Y-m-d') != $dateStart)
            return [
                'type' => 'error',
                'message' =>"Invalid date start.",
            ];

        $row = \App\ArchiveHomeConf::where('siteId', $siteId)
            ->where('tableIdentifier', $tableIdentifier)
            ->update([
                'eventsNumber' => $eventsNumber,
                'dateStart'    => $dateStart,
            ]);

        // delete events with dateStart less than
        \App\ArchiveHome::where('siteId', $siteId)
            ->where('tableIdentifier', $tableIdentifier)
            ->where('systemDate', '<', $dateStart)
            ->delete();

        // delete in addition events than events number
        $this->deleteInAdditionEvents($siteId, $tableIdentifier);

        return [
            'type' => 'success',
            'message' =>"Configuration was saved.",
        ];
    }

    // @param integer $siteId
    // @param string $tableIdentifier
    // This will delete in addition events than events mumber
    // @return void
    public function deleteInAdditionEvents($siteId, $tableIdentifier) {

        // get max events number
        $maxEventsNumber = \App\ArchiveHomeConf::where('siteId', $siteId)
            ->where('tableIdentifier', $tableIdentifier)
            ->first()->eventsNumber;

        $events = \App\ArchiveHome::orderBy('order', 'asc')
            ->where('siteId', $siteId)
            ->where('tableIdentifier', $tableIdentifier)
            ->skip($maxEventsNumber)
            ->take(1000)
            ->get();

        foreach ($events as $e)
            $e->delete();
    }

    public function store() {}

    // @param $id - event id,
    // @param $siteId,
    // @param $country,
    // @param $league,
    // @param $stringEventDate,
    // @param $homeTeam,
    // @param $awayTeam,
    // @param $predictionId,
    // @param $statusId,
    // update event in archive home
    // @return array()
    public function update(Request $r, $id)
    {
        $siteId = $r->input('siteId');
        $country = $r->input('country');
        $league = $r->input('league');
        $stringEventDate = $r->input('stringEventDate');
        $homeTeam = $r->input('homeTeam');
        $awayTeam = $r->input('awayTeam');
        $predictionId = $r->input('predictionId');
        $statusId = $r->input('statusId');

        $event = \App\ArchiveHome::find($id);
        if (!$event)
            return [
                'type' => 'error',
                'message' => "This event not exist anymore!",
            ];

        // get prediction according to site.
        $sitePrediction = \App\SitePrediction::where([
            ['siteId', '=', $siteId],
            ['predictionIdentifier', '=', $predictionId],
        ])->first();

        $event->country = $country;
        $event->league = $league;
        $event->stringEventDate = $stringEventDate;
        $event->homeTeam = $homeTeam;
        $event->awayTeam = $awayTeam;
        $event->predictionName = $sitePrediction->name;
        $event->predictionId = $predictionId;
        $event->statusId = $statusId;
        $event->save();

        return [
            'type' => 'success',
            'message' =>"Archive event was succesfful updated.",
        ];
    }

    public function destroy() {}

    // full archive home for a site
    // @ return array of objects
    public function getFullArchiveHome($id)
    {
        $site = \App\Site::find($id);
        if (!$site)
            return false;

        // results name and class
        $results = [];
        foreach (\App\SiteResultStatus::where('siteId', $id)->get()->toArray() as $k => $v)
           $results[$v['statusId']] = $v;

        // prediction
        $predictions = [];
        foreach (\App\SitePrediction::where('siteId', $id)->get()->toArray() as $k => $v)
           $predictions[$v['predictionIdentifier']] = $v;

        $currentDate = gmdate('Y-m-d H:i:s');
        $events = \App\ArchiveHome::where('siteId', 2)
            ->where('isVisible', '1')
            ->orderBy('order', 'ASC')
            ->orderBy('isVip', 'ASC')
            ->orderBy('eventDate', 'DESC')
            ->get()
            ->toArray();
        $vipFlags = [];

        $data = [];
        foreach ($events as $k => $e) {

            // add result statusName and statusClass and predictionName
            // only when event is tip (NOT noTip)
            if (!$e['isNoTip']) {
                $e['statusName'] = $results[$e['statusId']]['statusName'];
                $e['statusClass'] = $results[$e['statusId']]['statusClass'];
                $e['predictionName'] = $predictions[$e['predictionId']]['name'];
            }

            // change team name in prediction
            if (strpos($e['predictionName'], '{{team1}}') !== false) {
                $e['predictionName'] = str_replace('{{team1}}', $e['homeTeam'], $e['predictionName']);
            }

            if (strpos($e['predictionName'], '{{team2}}') !== false)
                $e['predictionName'] = str_replace('{{team2}}', $e['awayTeam'], $e['predictionName']);

            // vip flag
            if (!isset($vipFlags[$e['packageId']])) {
                $package = \App\Package::find($e['packageId']);
                $vipFlags[$e['packageId']] = $package->vipFlag;
            }
            $e['vipFlag'] = $vipFlags[$e['packageId']];

            $table = $e['tableIdentifier'];
            $data[$table][] = $e;
        }

        return $data;
    }
}

