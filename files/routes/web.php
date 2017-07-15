<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

use Illuminate\Http\Request;

$app->get('/', function () use ($app) {
    return $app->version() . dfsdsfsdfdsfsdfdsf;
});

$app->get('/test', ['middleware' => 'auth'], function () use ($app) {
    return $app->version();
});

// all routes for administration
$app->group(['prefix' => 'admin'], function ($app) {

    $app->get('/event', function() use ($app) {
        return \App\Event::all();
    });

    // Site CRUD
    // get all sites
    $app->get('/site', function() use ($app) {
        return \App\Site::all();
    });

    // get specific site by id
    $app->get("/site/{id}", function($id) use ($app) {
        return \App\Site::find($id);
    });

    // update a site
    $app->put("/site/{id}", function(Request $request, $id) use ($app) {
        $site = \App\Site::where('id', '=', $id)->first();

        dd($site);

        $site->name = $request->input('name');
        $site->save();
        return response()->json($site);
    });

    // store new site
    $app->post("/site", function(Request $request) use ($app) {
        $site = \App\Site::create($request->all());
        return response()->json($site);
    });

    // delete a site
    $app->delete("/site/{id}", function($id) use ($app) {
        $site = \App\Site::find($id);
        $site->delete();
        return response()->json('Removed successfully');
    });
});
