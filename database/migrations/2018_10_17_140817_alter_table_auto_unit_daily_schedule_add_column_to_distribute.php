<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableAutoUnitDailyScheduleAddColumnToDistribute extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('auto_unit_daily_schedule', function (Blueprint $table) {
            $table->boolean('to_distribute')->default(false);
            $table->unsignedInteger('odd_id')->nullable();
            $table->foreign('odd_id')
                ->references('id')->on('odd');
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
            $table->dropForeign(['odd_id']);

            $table->dropColumn('to_distribute');
            $table->dropColumn('odd_id');
        });
    }
}
