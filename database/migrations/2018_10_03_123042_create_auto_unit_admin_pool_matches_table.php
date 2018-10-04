<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAutoUnitAdminPoolMatchesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('auto_unit_admin_pool_matches', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pool_id');
            $table->unsignedInteger('match_id');
            $table->timestamps();
            
            $table->foreign('pool_id')
                ->references('id')->on('auto_unit_admin_pools')
                ->onDelete('cascade');
            $table->foreign('match_id')
                ->references('primaryId')->on('match')
                ->onDelete('cascade');
            $table->unique(["pool_id", "match_id"]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('auto_unit_admin_pool_matches');
    }
}
