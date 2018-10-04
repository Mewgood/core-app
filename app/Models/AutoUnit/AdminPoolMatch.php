<?php 

namespace App\Models\AutoUnit;

use Illuminate\Database\Eloquent\Model;

class AdminPoolMatch extends Model {

    protected $table = 'auto_unit_admin_pool_matches';

    protected $fillable = [
        'match_id',
        'pool_id',
    ];
}


