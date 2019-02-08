<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Site extends Controller
{
    // @return array of objects
    public function index()
    {
        return \App\Site::all();
    }

    /*
     * return object
     */
    public function get($id) {
        return \App\Site::find($id);
    }

    public function store(Request $r) {

        // TODO: check if new name is valid
        $name = $r->input('name');

        // Site name must be unique
        $site = \App\Site::where('name', $name)->first();
        if ($site !== null) {
            return response()->json([
                "type" => "error",
                "message" => "This site already exists!"
            ]);
        }

        $site = \App\Site::create([
            "name" => $name,
            "email" => $r->input('email'),
            "url" => $r->input('url'),
            "dateFormat" => $r->input('dateFormat'),
            "type" => $r->input('type'),
            "smtpHost" => $r->input('smtpHost'),
            "smtpPort" => $r->input('smtpPort'),
            "smtpUser" => $r->input('smtpUser'),
            "smtpPassword" => $r->input('smtpPassword'),
            "smtpEncryption" => $r->input('smtpEncryption'),
            "imapHost" => $r->input('imapHost'),
            "imapPort" => $r->input('imapPort'),
            "imapUser" => $r->input('imapUser'),
            "imapPassword" => $r->input('imapPassword'),
            "imapEncryption" => $r->input('imapEncryption'),
            "token" => bin2hex(random_bytes(16)),
        ]);

        return response()->json([
            "type" => "success",
            "message" => "New site was added with success!",
            "data" => \App\Site::where('name', $name)->first(),
        ]);
    }

    public function update(Request $r, $id) {
        $site = \App\Site::find($id);

        // Site not exists retur status not exists
        if ($site === null) {
            return response()->json([
                "type" => "error",
                "message" => "Site with id: $id not exists"
            ]);
        }

        $where = [
            ['name', '=', $r->input('name')],
            ['id', '!=', $id],
        ];
        if (\App\Site::where($where)->count())
            return response()->json([
                "type" => "error",
                "message" => "Site with name: " . $r->input('name') . " already exists!",
            ]);

        $site->name = $r->input('name');
        $site->email = $r->input('email');
        $site->url = $r->input('url');
        $site->dateFormat = $r->input('dateFormat');
        $site->type = $r->input('type');
        $site->smtpHost = $r->input('smtpHost');
        $site->smtpPort = $r->input('smtpPort');
        $site->smtpUser = $r->input('smtpUser');
        $site->smtpPassword = $r->input('smtpPassword');
        $site->smtpEncryption = $r->input('smtpEncryption');
        $site->imapHost = $r->input('imapHost');
        $site->imapPort = $r->input('imapPort');
        $site->imapUser = $r->input('imapUser');
        $site->imapPassword = $r->input('imapPassword');
        $site->imapEncryption = $r->input('imapEncryption');
        $site->email = $r->input('email');
        $site->save();

        return response()->json([
            "type" => "success",
            "message" => "General site information was updated with success!"
        ]);
    }

    // delete:
    //     - associated predictions
    //     - result status
    //     - association with packages
    //     - associated packages
    //     - associated packages predictions
    // @return array()
    public function destroy($id) {

        $site = \App\Site::find($id);

        // Site not exists retur status not exists
        if ($site === null) {
            return response()->json([
                "type" => "error",
                "message" => "Site with id: $id not exists"
            ]);
        }

        \App\SitePrediction::where('siteId', $id)->delete();
        \App\SiteResultStatus::where('siteId', $id)->delete();
        \App\SitePackage::where('siteId', $id)->delete();

        $packages = \App\Package::where('siteId', $id)->get()->toArray();
        foreach ($packages as $p) {
            \App\PackagePrediction::where('packageId', $p['id'])->delete();
            \App\Package::find($p['id'])->delete();
        }

        $site->delete();
        return response()->json([
            "type" => "success",
            "message" => "Site with id: $id was deleted with success!"
        ]);
    }

    // @return array of objects
    public function getIdsAndNames()
    {
        $data = \App\Site::select('site.id', 'site.name')
            ->join("package", "package.siteId", "site.id")
            ->groupBy("site.id")
            ->get()
            ->toArray();
            
        $sum = \App\Site::join("package", "package.siteId", "site.id")
            ->sum("paused_autounit");
        $data["all_state"] = \App\Models\Config::select("value")->where("name", "=", "autounit_all_state")->first();
        return $data;
    }

    /*
     * @return array()
     */
    public function getAvailableTables($siteId)
    {
        // will get this relation for packages model
        // each package has siteId
        return \App\Package::select('tableIdentifier')->distinct()->where('siteId', $siteId)->get();
    }

    // create records in archive_home_conf if not exists.
    // @param integer $id
    // @return void
    public function setArchiveHomeConf($id) {
        $packages = \App\Package::where('siteId', $id)
            ->get();
        foreach ($packages as $p) {
            $confExists = \App\ArchiveHomeConf::where('siteId', $p->siteId)
                ->where('tableIdentifier', $p->tableIdentifier)
                ->count();

            if (! $confExists) {
                \App\ArchiveHomeConf::create([
                    'siteId'          => $p->siteId,
                    'tableIdentifier' => $p->tableIdentifier,
                    'eventsNumber'    => 100,
                    'dateStart'       => '2017-01-01',
                ]);
            }
        }
    }

    // @param integer $id
    // get general configuration for site
    // @return array()
    public function getSiteConfiguration($id)
    {
        $site = \App\Site::find($id);
        if (!$site)
            return false;

        $packages = [];
        foreach (\App\Package::where('siteId', $id)->get() as $pack) {
            $packages[$pack->identifier]['paymentCodePaypal'] = $pack->paymentCodePaypal;
            $packages[$pack->identifier]['paymentCodeHipay'] = $pack->paymentCodeHipay;
        }


        return [
            'key'        => $site->token,
            'name'       => $site->name,
            'url'        => $site->url,
            'type'       => $site->type,
            'dateFormat' => $site->dateFormat,
            'imap'       => [
                'host'       => $site->imapHost,
                'port'       => $site->imapPort,
                'user'       => $site->imapUser,
                'password'   => $site->imapPassword,
                'encryption' => $site->imapEncryption,
            ],
            'packages' => $packages,
        ];
    }
}
