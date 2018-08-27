<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ArchiveBig extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /*
     * @return array()
     */
    public function index()
    {
    }

    // @param integer $id
    // @return event || null
    public function get($id) {
        return \App\ArchiveBig::find($id);
    }


    // @param integer $siteId
    // @param string $table
    // @param string $date
    // @return array()
    public function getMonthEvents(Request $r)
    {
        $siteId = $r->input('siteId');
        $tableIdentifier = $r->input('tableIdentifier');
        $date = $r->input('date');

        $data = \App\ArchiveBig::where('siteId', $siteId)
            ->where('tableIdentifier', $tableIdentifier)
            ->where('systemDate', '>=', $date . '-01')
            ->where('systemDate', '<=', $date . '-31')
			->orderBy('isVip', 'ASC')
            ->orderBy('eventDate', 'DESC')
            ->get()
            ->toArray();

		foreach ($data as $key => $value) {
			switch ($value['statusId']) {
				case 1:
					$data[$key]['statusId'] = 'Win';
				break;
				case 2:
					$data[$key]['statusId'] = 'Loss';
				break;
				case 3:
					$data[$key]['statusId'] = 'Draw';
				break;
				case 4:
					$data[$key]['statusId'] = 'PostP';
				break;
			}
		}

		return $data;
    }

    // @param $siteId,
    // @param $date format: Y-m,
    // set isPublishInSite 1 for all events fron site in selected month
    // @return array()
    public function publishMonth(Request $r)
    {
        $date   = $r->input('date');
        $siteId = $r->input('siteId');

        $first = $date . '-01';

        $carbon = Carbon::parse($first);
        $last = $carbon->endOfMonth()->toDateString();

        \App\ArchiveBig::where('siteId', $siteId)
            ->where('systemDate', '>=', $first)
            ->where('systemDate', '<=', $last)
            ->update(['isPublishInSite' => '1']);

        return [
            'type' => 'success',
            'message' =>"Events beetwen $first - $last was published in site: $siteId",
        ];


    }

    // get array with available years and month based on archived events.
    // @return array()
    public function getAvailableMounths()
    {
        $dates = [];

        $first = \App\ArchiveBig::select(\DB::raw('min(systemDate) as systemDate'))->get()[0]->systemDate;
        $last = \App\ArchiveBig::select(\DB::raw('max(systemDate) as systemDate'))->get()[0]->systemDate;

        $start    = (new \DateTime($first))->modify('first day of this month');
        $end      = (new \DateTime($last))->modify('first day of next month');
        $interval = \DateInterval::createFromDateString('1 month');
        $period   = new \DatePeriod($start, $interval, $end);

        foreach ($period as $dt) {
            $dates[] = [
                'year' => $dt->format("Y"),
                'month' => $dt->format("m"),
            ];
        }

        return array_reverse($dates);
    }

    // @param $id,
    // toogle show/hide an event from archiveBig
    // @return array()
    public  function toogleShowHide($id)
    {
        $event = \App\ArchiveBig::find($id);

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

    public function store() {}

    public function update() {}

    // @param $id,
    // @param $siteId,
    // @param $predictionId,
    // @param $StatusId,
    // update prediction and status
    // @return array()
    public function updatePredictionAndStatus(Request $r, $id)
    {
        $siteId = $r->input('siteId');
        $predictionId = $r->input('predictionId');
        $statusId = $r->input('statusId');

        $event = \App\ArchiveBig::find($id);
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

        $event->predictionName = $sitePrediction->name;
        $event->predictionId = $predictionId;
        $event->statusId = $statusId;
        $event->save();

        return [
            'type' => 'success',
            'message' =>"Prediction and status was succesfful updated.",
        ];
    }

    public function destroy() {}

    // @param integer $id
    // get archive-big events for site.
    // @return array() indexed by table idintifier.
    public function getFullArchiveBig($id)
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

        $events = \App\ArchiveBig::where('siteId', 2)
            ->where('isPublishInSite', '1')
            ->where('isVisible', '1')
            ->orderBy('order', 'ASC')
            ->orderBy('isVip', 'ASC')
            ->orderBy('eventDate', 'DESC')
            ->get()
            ->toArray();

        $vipFlags = [];

        $data = [];
        foreach ($events as $e) {

            // add result statusName and statusClass and predictionName
            // only when event is tip (NOT noTip)
            if (!$e['isNoTip']) {
                $e['statusName'] = $results[$e['statusId']]['statusName'];
                $e['statusClass'] = $results[$e['statusId']]['statusClass'];
                $e['predictionName'] = $predictions[$e['predictionId']]['name'];
            }

            // vip flag
            if (!isset($vipFlags[$e['packageId']])) {
                $package = \App\Package::find($e['packageId']);
                $vipFlags[$e['packageId']] = $package->vipFlag;
            }
            $e['vipFlag'] = $vipFlags[$e['packageId']];

            // change team name in prediction
            if (strpos($e['predictionName'], '{{team1}}') !== false) {
                $e['predictionName'] = str_replace('{{team1}}', $e['homeTeam'], $e['predictionName']);
            }

            if (strpos($e['predictionName'], '{{team2}}') !== false)
                $e['predictionName'] = str_replace('{{team2}}', $e['awayTeam'], $e['predictionName']);

            $table = $e['tableIdentifier'];
            $year  = date('Y', strtotime($e['systemDate']));
            $month = date('m', strtotime($e['systemDate']));
            $data[$table][$year][$month][] = $e;
        }
        return $data;
    }
}
