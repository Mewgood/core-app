<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTablePackageAddColumnsPauseState extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('site', function (Blueprint $table) {
            $table->dropColumn('manual_pause');
        });
        Schema::table('site', function (Blueprint $table) {
            $table->dropColumn('paused_autounit');
        });
        Schema::table('package', function (Blueprint $table) {
            $table->boolean('manual_pause')->default(false);
        });
        Schema::table('package', function (Blueprint $table) {
            $table->boolean('paused_autounit')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('site', function (Blueprint $table) {
            $table->boolean('manual_pause')->default(false);
        });
        Schema::table('site', function (Blueprint $table) {
            $table->boolean('paused_autounit')->default(false);
        });
        Schema::table('package', function (Blueprint $table) {
            $table->dropColumn('manual_pause');
        });
        Schema::table('package', function (Blueprint $table) {
            $table->dropColumn('paused_autounit');
        });
    }
}
