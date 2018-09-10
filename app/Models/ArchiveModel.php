<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Iluminate\Http\Request;
use Ixudra\Curl\Facades\Curl;

class ArchiveModel extends Model
{
    public static function sendDataToCMSSites($data, $site, $type)
    {
        $data["type"] = $type;
        $data["token"] = $site->token;

        $response = Curl::to($site->url . "admin/controller/api/betClient.php")
            ->withData([
                'data'   => $data
            ])
            ->asJson()
            ->post();
        return json_decode(json_encode($response), true);
    }
}