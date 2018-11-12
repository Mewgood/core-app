<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableAutoUnitMonthlySettingAlterColumnsMinOddMaxOdd extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('auto_unit_monthly_setting', function ($table) {
            $table->float('minOdd')->change();
            $table->float('maxOdd')->change();
        });
        Schema::table('odd', function ($table) {
            $table->float('odd')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
