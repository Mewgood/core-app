<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;

use App\Models\SubscriptionAlert;

class Package extends Controller
{

    public function index()
    {
        return \App\Package::all();
    }

    /*
     * return object
     */
    public function get($id) {
        return \App\Package::find($id);
    }

    // get ids and names for all pacckage associated with site
    // @param integer $siteId
    // @return array()
    public function getPackagesIdsAndNamesBySite($siteId)
    {
        return \App\Package::select('id', 'name')->where('siteId', $siteId)->get()->toArray();
    }

    /*
     * @return array()
     */
    public function store(Request $r) {

        // Todo: check inputs for validity

        $data = $r->all();

        $pack = \App\Package::create($data);

        //default section for package = nu
        $section = 'nu';

        // check if site has another package with same tip
        $packageSameTip = \App\Package::where('siteId', $data['siteId'])
            ->where('tipIdentifier', $data['tipIdentifier'])
            ->first();

        if ($packageSameTip) {
            $packageSection = \App\PackageSection::where('packageId', $packageSameTip->id)
                ->where('systemDate', gmdate('Y-m-d'))
                ->first();

            if ($packageSection)
                $section = $packageSection->section;
        }

        \App\PackageSection::create([
            'packageId'  => $pack->id,
            'section'    => $section,
            'systemDate' => gmdate('Y-m-d'),
        ]);

        // update home archive site configuration
        $siteController = new \App\Http\Controllers\Admin\Site();
        $siteController->setArchiveHomeConf($pack->siteId);

        return response()->json([
            "type" => "success",
            "message" => "New package: " . $r->input('name') . " was added with success!",
            "data" => $pack,
        ]);
    }

    /*
     * @return array()
     */
    public function update(Request $r, $id) {

        $pack = \App\Package::find($id);

        // Package not exists retur status not exists
        if ($pack === null) {
            return response()->json([
                "type" => "error",
                "message" => "Package with id: $id not exists anymore"
            ]);
        }

        // Todo: check inputs for validity
        $pack->update($r->input('data'));

        // update home archive site configuration
        $siteController = new \App\Http\Controllers\Admin\Site();
        $siteController->setArchiveHomeConf($pack->siteId);

        return response()->json([
            "type" => "success",
            "message" => "Package information " . $r->input('data')['name'] . " was updated with success!"
        ]);
    }

    /*
     * @return array()
     */
    public function destroy($id) {
        $pack = \App\Package::find($id);

        // Package not exists retur status not exists
        if ($pack === null) {
            return response()->json([
                "type" => "error",
                "message" => "Package with id: $id not exists"
            ]);
        }
        $pack->delete();

        // delete association with site
        \App\SitePackage::where('packageId', $id)->delete();

        // delete associated predictions
        \App\PackagePrediction::where('packageId', $id)->delete();

        return response()->json([
            "type" => "success",
            "message" => "Pack with id: $id was deleted with success!"
        ]);
    }

    /*
     * @return array()
     */
    public function getPackagesBySite($siteId)
    {
        $packages = \App\Package::where('siteId', $siteId)->get()->toArray();

        $predictions = \App\Prediction::all()->toArray();

        //iterate al packages and get predictions
        foreach ($packages as $k => $package) {

            // get allowed predictions
            $allowedPredictions = \App\PackagePrediction::where('packageId', $package['id'])->get()->toArray();

            $pred = $predictions;
            foreach ($pred as $kp => $p) {

                // make default association false
                $pred[$kp]['isAssociated'] = false;
                foreach ($allowedPredictions as $kap => $ap) {

                    // if association exists make it true
                    if ($p['identifier'] == $ap['predictionIdentifier'])
                       $pred[$kp]['isAssociated'] = true;
                }
            }

            // create new array and sort predicitons
            $data = [];
            foreach ($pred as $p) {
               $data[$p['group']]['predictions'][] = $p;
            }

            $packages[$k]['associatedPredictions'] = $data;
            //$packages[$k]['associatedPredictions'] = $allowedPredictions;

        }

        return $packages;
    }

    // This will move packages grouped by tip
    // real users -> no users || no users -> real users
    // ONLY if:
    //     - no package distributed tips was published in archive
    //     - no package distributed tips was send by email.
    // @param integer $packageId
    // @return void
    public function evaluateAndChangeSection($packageId)
    {

        // get all packages ids with same tip on same site
        $package = \App\Package::find($packageId);
        $packagesGroup = \App\Package::where('siteId', $package->siteId)
            ->where('tipIdentifier', $package->tipIdentifier)
            ->get();

        $packagesIds = [];
        foreach ($packagesGroup as $p)
            $packagesIds[] = $p->id;

        // get all distributed events for all packags
        $distributedEvents = \App\Distribution::whereIn('packageId', $packagesIds)
            ->where('systemDate', gmdate('Y-m-d'))
            ->get();

        // get all active subsciptions for package group
        $activeSubscriptions = \App\Subscription::where('status', 'active')
            ->whereIn('packageId', $packagesIds)
            ->get();

        // get current section for pacakge
        $sectionModel = \App\PackageSection::where('packageId', $packageId)
            ->where('systemDate', gmdate('Y-m-d'))
            ->first();
        $currentSection = $sectionModel ? $sectionModel->section : null;

        // check if need to move package in other section
        $sectionInstance = new \App\Src\Package\ChangeSection(
            $distributedEvents,
            $activeSubscriptions,
            $currentSection
        );
        $sectionInstance->evaluateSection();

        // do nothing if  there is set section and we do not need to change section
        if ($currentSection != null)
            if ($sectionInstance->getSection() == $currentSection)
                return;

        /** packages must be moved **/

        // delete already distributed Events
        \App\Distribution::whereIn('packageId', $packagesIds)
            ->where('systemDate', gmdate('Y-m-d'))
            ->delete();

        // update package section for today
        foreach ($packagesIds as $id) {
            $exists = \App\PackageSection::where('packageId', $id)
                ->where('systemDate', gmdate('Y-m-d'))->count();

            if ($exists) {
                \App\PackageSection::where('packageId', $id)
                    ->where('systemDate', gmdate('Y-m-d'))
                    ->update([
                        'section' => $sectionInstance->getSection()
                    ]);
                continue;
            }

            // not exist, so will create
            \App\PackageSection::create([
                'packageId' => $id,
                'section' => $sectionInstance->getSection(),
                'systemDate' => gmdate('Y-m-d'),
            ]);
        }

        return;
    }
    
    public function clearAlerts(Request $request)
    {
        SubscriptionAlert::clearAlerts($request->tableIdentifier, $request->tipIdentifier, $request->siteId);
        return response(200);
    }
}
