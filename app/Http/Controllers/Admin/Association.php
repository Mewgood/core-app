<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssociationModel;
use Illuminate\Http\Request;

class Association extends Controller
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

    /* @param string $tableIdentifier run|ruv|nun|nuv
     * @param string $date format: Y-m-d | 0 | null
     *     - $date = 0 | null => current date GMT
     * @return array()
     */
    public function index($tableIdentifier, $date)
    {
        if ($date === null || $date == 0)
            $date = gmdate('Y-m-d');

        $associations = \App\Association::where('type', $tableIdentifier)->where('systemDate', $date)->get();
        foreach ($associations as $association) {

            $unique = [];
            $count = 0;

            $association->status;
            $distributions = \App\Distribution::where('associationId', $association->id)->get();
            foreach ($distributions as $e) {
                if (array_key_exists($e->siteId, $unique))
                    if (array_key_exists($e->tipIdentifier, $unique[$e->siteId]))
                        continue;

                $unique[$e->siteId][$e->tipIdentifier] = true;
                $count++;
            }
            $association->distributedNumber = $count;
        }

        return $associations;
    }

    public function get() {}

    // get available packages according to table and event prediction
    // @param string  $table
    // @param integer $associateEventId
    // @param string | null $date
    // @return array();
    public function getAvailablePackages($table, $associateEventId, $date = null)
    {
        $data = [];
        $ineligiblePackageIds = [];
        $date = ($date === null) ? gmdate('Y-m-d') : $date;
        $data['event'] = \App\Association::find($associateEventId);
        $isVip = 0;

        if (!$data['event'])
            return response()->json([
                "type" => "error",
                "message" => "Event id: $associateEventId not exist anymore!"
            ]);

        // first get packagesIds acording to section
        $section = ($table === 'run' || $table === 'ruv') ? 'ru': 'nu';

        $packageSection = \App\PackageSection::select('packageId')
            ->where('section', $section)
            ->where('systemDate', $date)
            ->get();
            
        $packagesIds = [];
        foreach ($packageSection as $p)
            $packagesIds[] = $p->packageId;

        // only vip or normal package according to table
        foreach ($packagesIds as $k => $id) {

            // table is vip exclude normal packages
            if ($table == "ruv" || $table == "nuv") {
                $isVip = 1;
                if (\App\Package::where('id', $id)->where('isVip', '0')->count())
                    unset($packagesIds[$k]);
                continue;
            }

            // table is normal exclude vip packages
            if (\App\Package::where('id', $id)->where('isVip', '1')->count())
                unset($packagesIds[$k]);
        }

        // sort by event type tip or noTip
        foreach ($packagesIds as $k => $id) {

            // event is no tip -> exclude packages who have tip events
            if ($data['event']->isNoTip) {
                $hasEvents = \App\Distribution::where('packageId', $id)
                    ->where('systemDate', $date)
                    ->where('isNoTip', '0')->count();

                if ($hasEvents)
                    unset($packagesIds[$k]);
                continue;
            }

            // there is event unset packages hwo has noTip
            $hasNoTip = \App\Distribution::where('packageId', $id)
                ->where('systemDate', $date)
                ->where('isNoTip', '1')->count();

            if ($hasNoTip) {
                unset($packagesIds[$k]);
                continue;
            }

            // there is event unset packages not according to betType
            $packageAcceptPrediction = \App\PackagePrediction::where('packageId', $id)
                ->where('predictionIdentifier', $data['event']->predictionId)
                ->count();

            if (! $packageAcceptPrediction) {
                $ineligiblePackageIds[] = $packagesIds[$k];
                unset($packagesIds[$k]);
            }
        }

        // Now $packagesIds contain only available pacakages

        $keys = [];
        $increments = 0;
        $packages = \App\Package::whereIn('id', $packagesIds)->get();
        foreach ($packages as $p) {

            $site = \App\Site::find($p->siteId);

            // create array
            if (!array_key_exists($site->name, $keys)) {
                $keys[$site->name] = $increments;
                $increments++;
            }

            // check if event alredy exists in tips distribution
            $distributionExists = \App\Distribution::where('associationId', $data['event']->id)
                ->where('packageId', $p->id)
                ->count();

            // get number of associated events with package on event systemDate
            $eventsExistsOnSystemDate = \App\Distribution::where('packageId', $p->id)
                ->where('systemDate', $date)
                ->count();
            $tipsDifference = $eventsExistsOnSystemDate - $p->tipsPerDay;

            // 0 - unfilled
            // 1 - filled
            // 2 - inelegible
            if ($tipsDifference < 0) {
                $data['sites'][0][$site->name]['tipIdentifier'][$p->tipIdentifier]["siteName"] = $site->name;
                $data['sites'][0][$site->name]['tipIdentifier'][$p->tipIdentifier]["eligible"] = true;
                $data['sites'][0][$site->name]['tipIdentifier'][$p->tipIdentifier]["tipsDifference"] = $tipsDifference;
                $data['sites'][0][$site->name]['tipIdentifier'][$p->tipIdentifier]['packages'][] = [
                    'id' => $p->id,
                    'name' => $p->name,
                    'tipsPerDay' => $p->tipsPerDay,
                    'eventIsAssociated' => $distributionExists,
                    'packageAssociatedEventsNumber' => $eventsExistsOnSystemDate
                ];
            } else {
                $data['sites'][1][$site->name]['tipIdentifier'][$p->tipIdentifier]["siteName"] = $site->name;
                $data['sites'][1][$site->name]['tipIdentifier'][$p->tipIdentifier]["eligible"] = true;
                $data['sites'][1][$site->name]['tipIdentifier'][$p->tipIdentifier]["tipsDifference"] = $tipsDifference;
                $data['sites'][1][$site->name]['tipIdentifier'][$p->tipIdentifier]['packages'][] = [
                    'id' => $p->id,
                    'name' => $p->name,
                    'tipsPerDay' => $p->tipsPerDay,
                    'eventIsAssociated' => $distributionExists,
                    'packageAssociatedEventsNumber' => $eventsExistsOnSystemDate
                ];
            }
        }

        $data['sites'][2] = Association::getUnAvailablePackages($packagesIds, $data, $date, $isVip, $section);

        if (!isset($data['sites'][0])) {
            $data['sites'][0] = [];
        }
        if (!isset($data['sites'][1])) {
            $data['sites'][1] = [];
        }
        if (!isset($data['sites'][2])) {
            $data['sites'][2] = [];
        }
        return $data;
    }
    
    public static function getUnAvailablePackages($eligiblePackageIds, $association, $date, $isVip, $section) {
        $data = [];

        $ineligiblePackages = \App\Package::select(
                "package.id",
                "package.name",
                "package.tipsPerDay",
                "package.tipIdentifier",
                "distribution.id AS distributionId",
                "site.name AS siteName",
                "subscription.id AS subscriptionId"
            )
            ->join("site", "site.id", "package.siteId")
            ->join("package_prediction", "package_prediction.packageId", "package.id")
            ->leftJoin("distribution", "distribution.packageId", "package.id")
            ->leftJoin("subscription", "subscription.packageId", "package.id")
            ->whereNotIn('package.id', $eligiblePackageIds)
            ->where("package.isVip", "=", $isVip)
            ->when($association['event']->isNoTip, function ($query) { // event is no tip -> exclude packages who have tip events
                return $query->where("distribution.systemDate", $date)
                    ->where("distribution.isNoTip", "=", 1);
            })
            ->when($section === "nu", function($query) {
                return $query->whereNull("subscription.id");
            })
            ->where("package_prediction.predictionIdentifier", "!=", $association['event']->predictionId)
            ->groupBy("package.id")
            ->get();

        foreach ($ineligiblePackages as $p) {            
            // check if event alredy exists in tips distribution
            $distributionExists = \App\Distribution::where('associationId', $association['event']->id)
                ->where('packageId', $p->id)
                ->count();

            // get number of associated events with package on event systemDate
            $eventsExistsOnSystemDate = \App\Distribution::where('packageId', $p->id)
                ->where('systemDate', $date)
                ->count();

            $tipsDifference = $eventsExistsOnSystemDate - $p->tipsPerDay;
            $data[$p->siteName]['tipIdentifier'][$p->tipIdentifier]["siteName"] = $p->siteName;
            $data[$p->siteName]['tipIdentifier'][$p->tipIdentifier]["eligible"] = false;
            $data[$p->siteName]['tipIdentifier'][$p->tipIdentifier]["tipsDifference"] = $tipsDifference;
            $data[$p->siteName]['tipIdentifier'][$p->tipIdentifier]['packages'][] = [
                'id' => $p->id,
                'name' => $p->name,
                'tipsPerDay' => $p->tipsPerDay,
                'eventIsAssociated' => $distributionExists,
                'packageAssociatedEventsNumber' => $eventsExistsOnSystemDate
            ];
        }
        return $data;
    }

    // create new associations
    // @param array() $eventsIds
    // @param string  $table
    // @param string  $systemDate
    // @return array()
    public function store(Request $r)
    {
        $events = $r->input('events');
        $systemDate = $r->input('systemDate');

        if (empty($events))
            return response()->json([
                "type" => "error",
                "message" => "You must select at least one event"
            ]);

        // TODO check $systemDate is a vlid date

        $notFound = 0;
        $alreadyExists = 0;
        $success = 0;
        $returnMessage = '';

        foreach ($events as $item) {
            $vip = ($item["table"] === 'ruv' || $item["table"] === 'nuv') ? '1' : '';
        
            if (!\App\Event::find($item["id"])) {
                $notFound++;
                continue;
            }

            $event = \App\Event::find($item["id"])->toArray();

            // Check if already exists in association table
            if (\App\Association::where([
                ['eventId', '=', (int)$item["id"]],
                ['type', '=', $item["table"]],
                ['predictionId', '=', $event['predictionId']],
            ])->count()) {
                $alreadyExists++;
                continue;
            }

            $event['eventId'] = (int)$event['id'];
            unset($event['id']);
            unset($event['created_at']);
            unset($event['updated_at']);

            $event['isNoTip'] = '';
            $event['isVip'] = $vip;
            $event['type'] = $item["table"];
            $event['systemDate'] = $systemDate;
			
			// get the aliases - added by GDM
			$homeTeamAlias = \App\Models\Team\Alias::where('teamId', $event['homeTeamId'] )->first();
			if( $homeTeamAlias && $homeTeamAlias->alias && $homeTeamAlias->alias != '' ) {
				$event['homeTeam'] = $homeTeamAlias->alias;
			}		
			$awayTeamAlias = \App\Models\Team\Alias::where('teamId', $event['awayTeamId'] )->first();
			if( $awayTeamAlias && $awayTeamAlias->alias && $awayTeamAlias->alias != '' ) {
				$event['awayTeam'] = $awayTeamAlias->alias;
			}		
			$leagueAlias = \App\Models\League\Alias::where('leagueId', $event['leagueId'] )->first();
			if( $leagueAlias && $leagueAlias->alias && $leagueAlias->alias != '' ) {
				$event['league'] = $leagueAlias->alias;
			}
			
			$countryAlias = \App\Models\Country\Alias::where('countryCode', $event['countryCode'] )->first();
			if( $countryAlias && $countryAlias->alias && $countryAlias->alias != '' ) {
				$event['country'] = $countryAlias->alias;
			}
			

            \App\Association::create($event);
            $success++;
        }

        if ($notFound)
            $returnMessage .= $notFound . " - events not found (maybe was deleted)\r\n";

        if ($alreadyExists)
            $returnMessage .= $alreadyExists . " - already associated with this table\r\n";

        if ($success)
            $returnMessage .= $success . " - events was added with success\r\n";

        return response()->json([
            "type" => "success",
            "message" => $returnMessage
        ]);
    }

    // add no tip to a table
    // @param string $table
    // @param string $systemDate
    // @return array()
    public function addNoTip(Request $r)
    {
        $table = $r->input('table');
        $systemDate = $r->input('systemDate');

        $errors = [];
        $isErrored = false;

        foreach ($table as $item) {
            $validMessage = AssociationModel::validate($item, $systemDate);
            if ($validMessage["type"] == "error") {
                $isErrored = true;
                $errors[] = $validMessage;
            }
            AssociationModel::validate($item, $systemDate);
        }
        
        if ($isErrored) {
            return [
                'type' => 'error',
                'message' => "Failed to insert",
                'data' => $errors,
            ];
        }
        
        foreach ($table as $item) {
            $a = new \App\Association();
            $a->type = $item["table"];
            $a->isNoTip = '1';

            if ($item["table"] === 'ruv' || $item["table"] === 'nuv')
                $a->isVip = '1';

            $a->systemDate = $systemDate;
            $a->save();

            return response()->json([
                "type" => "success",
                "message" => "No Tip was added with success!",
            ]);
        }
    }

    public function update() {}

    public function destroy($id) {

        $association = \App\Association::find($id);

        // assoociation not exists retur status not exists
        if ($association === null) {
            return response()->json([
                "type" => "error",
                "message" => "Event with id: $id not exists"
            ]);
        }

        // could not delete an already distributed association
        if (\App\Distribution::where('associationId', $id)->count())
        return response()->json([
            "type" => "error",
            "message" => "Before delete event: $id  you must delete all distribution of this!"
        ]);

        $association->delete();
        return response()->json([
            "type" => "success",
            "message" => "Site with id: $id was deleted with success!"
        ]);
    }
}
