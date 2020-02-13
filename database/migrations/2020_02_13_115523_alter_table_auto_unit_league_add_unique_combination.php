<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableAutoUnitLeagueAddUniqueCombination extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('auto_unit_league', function (Blueprint $table) {
            $table->dropColumn('date');
            $table->dropColumn('type');
            $table->unique(['siteId', 'tipIdentifier', 'leagueId']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('auto_unit_league', function (Blueprint $table) {
            $table->string('date')->index();
            $table->string('type')->index();
        });
    }
}
