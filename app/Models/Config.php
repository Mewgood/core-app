<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Config extends Model {

    protected $table = 'configs';

    protected $fillable = [
        'id',
        'name',
        'value'
    ];
}
