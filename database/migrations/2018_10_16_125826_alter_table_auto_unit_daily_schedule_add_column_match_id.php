<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableAutoUnitDailyScheduleAddColumnMatchId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('auto_unit_daily_schedule', function (Blueprint $table) {
            $table->unsignedInteger('match_id')->nullable();
            $table->foreign('match_id')
                ->references('primaryId')->on('match');
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
            $table->dropForeign(['match_id']);
            $table->dropColumn('match_id');
        });
    }
}
