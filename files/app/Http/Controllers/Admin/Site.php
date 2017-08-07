<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class Site extends Controller
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

    public function index()
    {
        return \App\Site::all();
    }

    public function get() {}

    public function store() {}

    public function update() {}

    public function destroy() {}

    /*
     * @return array()
     */
    public function getIdsAndNames()
    {
        return \App\Site::select('id', 'name')->get();
    }

}