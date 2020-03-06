<?php

namespace App\Http\Controllers\Admin;

use App\Prediction;
use Illuminate\Http\Request;
use App\Models\AssociationModel;
use App\Http\Controllers\Controller;
use App\Models\AutoUnit\DailySchedule;
use App\Models\AutoUnit\MonthlySetting;

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
        $todayYM = gmdate("Y-m");
        $todayYMD = gmdate("Y-m-d");
        
        $data['sites'][2] = Association::getUnAvailablePackages($packagesIds, $data, $date, $isVip, $section, $data['event']);

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
            
            if ($section == "nu") {
                $autounit = MonthlySetting::where("siteId", "=", $p->siteId)
                    ->where("tipIdentifier", "=", $p->tipIdentifier)
                    ->where("tableIdentifier", "=", $p->tableIdentifier)
                    ->where("date", "=", $todayYM)
                    ->first();

                if (
                    $autounit &&
                    (float)$data['event']->odd >= (float)$autounit->minOdd && 
                    (float)$data['event']->odd <= (float)$autounit->maxOdd
                ) {

                    if ($tipsDifference >= 0) {
                        $this->mapAssociationModalData($data, 3, $site, $p, $tipsDifference, $distributionExists, $eventsExistsOnSystemDate, true);
                    } else {
                        $this->mapAssociationModalData($data, 4, $site, $p, $tipsDifference, $distributionExists, $eventsExistsOnSystemDate, true);
                    }
                } else if (!$autounit) {
                    if ($tipsDifference < 0) {
                        $this->mapAssociationModalData($data, 0, $site, $p, $tipsDifference, $distributionExists, $eventsExistsOnSystemDate, true);
                    } else {
                        $this->mapAssociationModalData($data, 1, $site, $p, $tipsDifference, $distributionExists, $eventsExistsOnSystemDate, true);
                    }
                } else {
                    // not eligible
                    $this->mapAssociationModalData($data, 2, $site, $p, $tipsDifference, $distributionExists, $eventsExistsOnSystemDate, false);
                }
            }
            // 0 - unfilled
            // 1 - filled
            // 2 - inelegible
            // 3 - AU filled
            // 4 - AU unfilled
            if ($section == "ru") {
                if ($tipsDifference < 0) {
                    $this->mapAssociationModalData($data, 0, $site, $p, $tipsDifference, $distributionExists, $eventsExistsOnSystemDate, true);
                } else {
                    $this->mapAssociationModalData($data, 1, $site, $p, $tipsDifference, $distributionExists, $eventsExistsOnSystemDate, true);
                }
            }
        }

        if (!isset($data['sites'][0])) {
            $data['sites'][0] = [];
        }
        if (!isset($data['sites'][1])) {
            $data['sites'][1] = [];
        }
        if (!isset($data['sites'][2])) {
            $data['sites'][2] = [];
        }
        if ($section == "nu") {
            if (!isset($data['sites'][3])) {
                $data['sites'][3] = [];
            }
            if (!isset($data['sites'][4])) {
                $data['sites'][4] = [];
            }
        }
        
        return $data;
    }
    
    public static function getUnAvailablePackages($eligiblePackageIds, $association, $date, $isVip, $section, $event) {
        $data = [];

        $ineligiblePackages = \App\Package::select(
                "package.id",
                "package.name",
                "package.tipsPerDay",
                "package.tipIdentifier",
                "distribution.id AS distributionId",
                "site.name AS siteName"
            )
            ->join("site", "site.id", "package.siteId")
            ->join("package_prediction", "package_prediction.packageId", "package.id")
            ->join("package_section", "package_section.packageId", "package.id")
            ->leftJoin("distribution", "distribution.packageId", "package.id")
            ->whereNotIn('package.id', $eligiblePackageIds)
            ->where("package.isVip", "=", $isVip)
            ->where("package_section.section" , "=", $section)
            ->where("package_section.systemDate" , "=", $date)
            ->when($association['event']->isNoTip, function ($query, $date) { // event is no tip -> exclude packages who have tip events
                return $query->where("distribution.systemDate", $date)
                    ->where("distribution.isNoTip", "=", 1);
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
            $data['sites'][0][$p->siteName]['tipIdentifier'][$p->tipIdentifier]["toDistribute"] = $event->to_distribute;
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
            $errors[] = $validMessage;
            if ($validMessage["type"] == "error") {
                $isErrored = true;
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
    
    private function mapAssociationModalData(&$data, $eligibilityCase, $site, $package, $tipsDifference, $distributionExists, $eventsExistsOnSystemDate, $eligible)
    {
        $data['sites'][$eligibilityCase][$site->name]['tipIdentifier'][$package->tipIdentifier]["siteName"] = $site->name;
        $data['sites'][$eligibilityCase][$site->name]['tipIdentifier'][$package->tipIdentifier]["toDistribute"] = $data['event']->to_distribute;
        $data['sites'][$eligibilityCase][$site->name]['tipIdentifier'][$package->tipIdentifier]["eligible"] = $eligible;
        $data['sites'][$eligibilityCase][$site->name]['tipIdentifier'][$package->tipIdentifier]["tipsDifference"] = $tipsDifference;
        $data['sites'][$eligibilityCase][$site->name]['tipIdentifier'][$package->tipIdentifier]['packages'][] = [
            'id' => $package->id,
            'name' => $package->name,
            'tipsPerDay' => $package->tipsPerDay,
            'eventIsAssociated' => $distributionExists,
            'packageAssociatedEventsNumber' => $eventsExistsOnSystemDate
        ];
    }

    public function displayAssociationDetail($id)
    {
        $association = AssociationModel::findOrFail($id);
        $predictions = Prediction::get();

        return response([
            "association" => $association,
            "predictions" => $predictions
        ]);
    }

    public function updatePrediction(Request $request)
    {
        $association = AssociationModel::findOrFail($request->associationId);
        $existingAssociation = AssociationModel::where("predictionId", "=", $request->predictionId)
            ->where("systemDate", "=", $association->systemDate)
            ->where("homeTeamId", "=", $association->homeTeamId)
            ->where("awayTeamId", "=", $association->awayTeamId)
            ->where("leagueId", "=", $association->leagueId)
            ->where("id", "!=", $association->id)
            ->where("type", "=", $association->type)
            ->exists();

        if ($existingAssociation) {
            return response([
                "error" => [
                    "message" => "Prediction already exists!"
                ]
            ]);
        }

        $association->odd = $request->odd;
        $association->predictionId = $request->predictionId;

        $statusByScore = new \App\Src\Prediction\SetStatusByScore($association->result, $association->predictionId);
        $statusByScore->evaluateStatus();
        $statusId = $statusByScore->getStatus();
        $association->statusId = $statusId;
        $association->update();

        $distributions = \App\Distribution::where("associationId", "=", $association->id)->groupBy("associationId", "siteId")->get();
        $response = [];
        $response["association"] = $association;

        foreach ($distributions as $distribution) {
            if (!$distribution->isPublish) {
                $temp = $distribution->updateDistribution($association);
                if ($temp) {
                    $response["notPublished"][$distribution->siteId] = $temp;
                }
            } else {
                $temp = $distribution->updateDistribution($association);
                if ($temp) {
                    $response["published"][$distribution->siteId] = $temp;
                }
            }
        }
        return response($response);
    }

    public function updatePublishedPredictions(Request $request)
    {
        $previousAssociation = null;
        $response = [];

        if ($request->has("associationId") && $request->has("siteIds")) {
            $association = AssociationModel::findOrFail($request->associationId);
    
            foreach ($request->siteIds as $key => $value) {
                $distributions = \App\Distribution::where("associationId", "=", $association->id)
                    ->where("siteId", "=", $value)
                    ->groupBy("associationId", "siteId")
                    ->get();
    
                foreach ($distributions as $distribution) {
                    switch ($request->actions[$key]) {
                        case "update":
                            $temp = $distribution->updatePublishedDistribution($association);
                            if ($temp) {
                                $response["sites"][$distribution->siteId] = $temp;
                            }
                        break;
    
                        case "change":
                            if ($distribution->provider == "autounit") {
                                $temp = $distribution->changePublishedAutounit($association);
                                if ($temp) {
                                    $response["sites"][$distribution->siteId] = $temp;
                                }
                            } else {
                                $temp = $distribution->removePublishedDistribution();
                                if ($temp) {
                                    $response["sites"][$distribution->siteId] = $temp;
                                }
                            }
                        break;
    
                        default:
                    }
                }

                if ($request->actions[$key] == "keep") {
                    $distributions = \App\Distribution::where("associationId", "=", $association->id)
                        ->where("siteId", "=", $value)
                        ->get();

                    foreach ($distributions as $distribution) {
                        if (!$previousAssociation) {
                            $statusByScore = new \App\Src\Prediction\SetStatusByScore($distribution->result, $distribution->predictionId);
                            $statusByScore->evaluateStatus();
                            $statusId = $statusByScore->getStatus();

                            $this->statusId = $statusId;
                            $this->update();

                            $event = \App\Event::where("countryCode" , "=", $distribution->countryCode)
                                ->where("leagueId"    , "=", $distribution->leagueId)
                                ->where("awayTeamId"  , "=", $distribution->awayTeamId)
                                ->where("odd"         , "=", $distribution->odd)
                                ->where("predictionId", "=", $distribution->predictionId)
                                ->where("result"      , "=", $distribution->result)
                                ->where("statusId"    , "=", $distribution->statusId)
                                ->where("eventDate"   , "=", $distribution->eventDate)
                                ->first();
        
                            if (!$event) {
                                $previousEvent = \App\Event::find($distribution->eventId);
                                $event = \App\Event::create([
                                    "matchId" => $previousEvent->matchId,
                                    "source" => $distribution->source,
                                    "provider" => $distribution->provider,
                                    "type" => $association->type,
                                    "isNoTip" => $distribution->isNoTip,
                                    "isVip" => $distribution->isVip,
                                    "country" => $distribution->country,
                                    "countryCode" => $distribution->countryCode,
                                    "league" => $distribution->league,
                                    "leagueId" => $distribution->leagueId,
                                    "homeTeam" => $distribution->homeTeam,
                                    "homeTeamId" => $distribution->homeTeamId,
                                    "awayTeam" => $distribution->awayTeam,
                                    "awayTeamId" => $distribution->awayTeamId,
                                    "odd" => $distribution->odd,
                                    "predictionId" => $distribution->predictionId,
                                    "result" => $distribution->result,
                                    "statusId" => $statusId,
                                    "eventDate" => $distribution->eventDate,
                                    "systemDate" => $distribution->systemDate,
                                    "to_distribute" => $distribution->to_distribute
                                ]);
                            }

                            $previousAssociation = AssociationModel::create([
                                "eventId" => $event->id,
                                "source" => $distribution->source,
                                "provider" => $distribution->provider,
                                "type" => $association->type,
                                "isNoTip" => $distribution->isNoTip,
                                "isVip" => $distribution->isVip,
                                "country" => $distribution->country,
                                "countryCode" => $distribution->countryCode,
                                "league" => $distribution->league,
                                "leagueId" => $distribution->leagueId,
                                "homeTeam" => $distribution->homeTeam,
                                "homeTeamId" => $distribution->homeTeamId,
                                "awayTeam" => $distribution->awayTeam,
                                "awayTeamId" => $distribution->awayTeamId,
                                "odd" => $distribution->odd,
                                "predictionId" => $distribution->predictionId,
                                "result" => $distribution->result,
                                "statusId" => $statusId,
                                "eventDate" => $distribution->eventDate,
                                "systemDate" => $distribution->systemDate,
                                "to_distribute" => $distribution->to_distribute
                            ]);
                        }

                        $distribution->associationId = $previousAssociation->id;
                        $distribution->statusId = $statusId;
                        $distribution->update();
        
                        $response["sites"][$distribution->siteId] = [
                            "site" => $distribution->site,
                            "status" => "Kept",
                            "type" => $distribution->provider
                        ];
                    }
                }
            }
        }
        return response($response);
    }
}
