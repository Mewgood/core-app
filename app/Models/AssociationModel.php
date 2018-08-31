<?php 
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssociationModel extends Model 
{
    protected $table = 'association';
    
    public static function validate($item, $systemDate) {
        // check if already exists no tip in selected date
        if (AssociationModel::where('type', $item["table"])
            ->where('isNoTip', '1')
            ->where('systemDate', $systemDate)->count())
        {
            return [
                "type" => "error",
                "message" => "Already exists no tip table in selected date",
            ];
        }
        return [
            'data' => $item,
            'type' => 'success'
        ];
    }
}
