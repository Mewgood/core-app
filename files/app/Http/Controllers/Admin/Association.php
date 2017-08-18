<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

        return \App\Association::where('type', $tableIdentifier)->where('systemDate', $date)->get();
    }

    public function get() {}

    public function store() {}

    public function update() {}

    public function destroy($id) {

        $association = \App\Association::find($id);

        // Site not exists retur status not exists
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
