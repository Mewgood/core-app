<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTablePaclageSetDefaultPauseState extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('package', function ($table) {
            $table->boolean('manual_pause')->default(true)->change();
            $table->boolean('paused_autounit')->default(true)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('package', function ($table) {
            $table->boolean('manual_pause')->default(false)->change();
            $table->boolean('paused_autounit')->default(false)->change();
        });
    }
}
