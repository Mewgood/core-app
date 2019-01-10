<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableAutoUnitDailyScheduleModifyColumnIsFromAdminPool extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('auto_unit_daily_schedule', function ($table) {
            $table->boolean('is_from_admin_pool')->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('auto_unit_daily_schedule', function ($table) {
            $table->boolean('is_from_admin_pool')->default(NULL)->change();
        });
    }
}
