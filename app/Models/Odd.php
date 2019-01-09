<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Odd extends Model {

    protected $table = 'odd';

    protected $fillable = [
        'id',
        'matchId',
        'leagueId',
        'predictionId',
        'odd'
    ];
}
