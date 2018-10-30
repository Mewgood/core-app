<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableAutoUnitDailyScheduleAddColumnInvalidMatches extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('auto_unit_daily_schedule', function (Blueprint $table) {
            $table->longtext('invalid_matches')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('auto_unit_daily_schedule', function (Blueprint $table) {
            $table->dropColumn('invalid_matches');
        });
    }
}
