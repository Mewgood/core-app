<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableAutoUnitDailyScheduleAddColumnIsFromAdminPool extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('auto_unit_daily_schedule', function (Blueprint $table) {
            $table->boolean('is_from_admin_pool')->nullable();
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
            $table->dropColumn('is_from_admin_pool');
        });
    }
}
