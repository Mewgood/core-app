<?php

namespace App\Http\Controllers\Admin\Client;

use App\Http\Controllers\Controller;
use Iluminate\Http\Request;
use Ixudra\Curl\Facades\Curl;
use App\Models\ArchiveModel;

class TriggerAction extends Controller
{
    // send client (site) data to update his configuration.
    // @param integer $id
    // @return array()
    public function updateConfiguration($id)
    {
        $site = \App\Site::find($id);
        if (!$site)
            return [
                'type' => 'error',
                'message' => "Site id: $id not exist enymore.",
            ];

        $siteInstance = new \App\Http\Controllers\Admin\Site();
        $conf = base64_encode(json_encode($siteInstance->getSiteConfiguration($id)));

        $response = Curl::to($site->url)
            ->withData([
                'route'  => 'api',
                'key'    => $site->token,
                'method' => 'updateSiteConfiguration',
                'data'   => $conf,
            ])
            ->post();

        $response = $this->decodeJSON($response);

        // if success update isConnected
        if (isset($response['success']) && $response['success']) {
            $site->isConnect = 1;
            $site->save();
        }

        return $this->checkResponse(json_encode($response));
    }

    // send client (site) his archive big for store.
    // @param integer $id
    // @return array()
    public function updateArchiveBig($id)
    {
        $site = \App\Site::find($id);
        if (!$site)
            return [
                'type' => 'error',
                'message' => "Site id: $id not exist enymore.",
            ];

        $archiveBigInstance = new \App\Http\Controllers\Admin\ArchiveBig();
        $archive = base64_encode(json_encode($archiveBigInstance->getFullArchiveBig($id)));

        if (strtolower($site->type) == "cms") {
            $archive = $archiveBigInstance->getFullArchiveBig($id);
            $response = ArchiveModel::sendDataToCMSSites($archive, $site, "archiveBig");
        } else {
            $archive = base64_encode(json_encode($archiveBigInstance->getFullArchiveBig($id)));
            $response = Curl::to($site->url)
                ->withData([
                    'route'  => 'api',
                    'key'    => $site->token,
                    'method' => 'updateArchiveBig',
                    'data'   => $archive,
                ])
                ->post();
            $response = $this->decodeJSON($response);
        }

        $resp = $this->checkResponse($response, $site, "archiveBig");

        if ($resp['type'] == 'success') {
            \App\ArchivePublishStatus::where('siteId', $site->id)
            ->where('type', 'archiveBig')
            ->delete();
        }

        return $resp;
    }

    // send client (site) his archive home for store
    // @param integer $id
    // @return array()
    public function updateArchiveHome($id)
    {
        $site = \App\Site::find($id);
        if (!$site)
            return [
                'type' => 'error',
                'message' => "Site id: $id not exist enymore.",
            ];

        $archiveHomeInstance = new \App\Http\Controllers\Admin\ArchiveHome();

        if (strtolower($site->type) == "cms") {
            $archive = $archiveHomeInstance->getFullArchiveHome($id);
            $response = ArchiveModel::sendDataToCMSSites($archive, $site, "archiveHome");
        } else {
            $archive = base64_encode(json_encode($archiveHomeInstance->getFullArchiveHome($id)));
            $response = Curl::to($site->url)
            ->withData([
                'route'   => 'api',
                'key'     => $site->token,
                'method'  => 'updateArchiveHome',
                'data'    => $archive,
            ])
            ->post();
            $response = $this->decodeJSON($response);
        }
        $resp = $this->checkResponse($response, $site, "archiveHome");

        if ($resp['type'] == 'success') {
            \App\ArchivePublishStatus::where('siteId', $site->id)
            ->where('type', 'archiveHome')
            ->delete();
        }

        return $resp;
    }

    private function checkResponse($response, $site, $type)
    {
        if (!$response)
            return [
                'type' => 'error',
                'message' => 'Client site not respond, check Website Url and client site availability in browser.',
                'site' => $site->url,
                'archive' => $type
            ];

        return [
            'type' => $response['success'] ? 'success' : 'error',
            'message' => $response['message'],
        ];
    }

    private function decodeJSON($json)
    {
        // remove unused characters from json
        if (0 === strpos(bin2hex($json), 'efbbbf')) {
            $json = substr($json, 3);
        }

        return (array) json_decode($json, true);
    }
}
