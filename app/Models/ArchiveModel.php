<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Match;
use App\Site;
use Iluminate\Http\Request;
use Ixudra\Curl\Facades\Curl;

class ArchiveModel extends Model
{
    public static function sendDataToCMSSites($data)
    {
        $cmsSites = Site::getCMSSites();
        foreach($cmsSites as $cmsSite) {
            $response = Curl::to($cmsSite->url . "admin/controller/api/betClient.php")
                ->withData([
                    'data'   => $data
                ])
                ->asJson()
                ->post();
        }
    }
}