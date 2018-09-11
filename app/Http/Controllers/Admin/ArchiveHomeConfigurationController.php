<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ArchiveHomeConfiguration;

class ArchiveHomeConfigurationController extends Controller
{
    public function getSiteConfiguration($token)
    {
        $data = ArchiveHomeConfiguration::getSiteConfiguration($token);
        return response($data);
    }
}
