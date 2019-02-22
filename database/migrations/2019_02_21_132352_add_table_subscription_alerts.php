<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTableSubscriptionAlerts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('subscription_alerts', function (Blueprint $table) {
            $table->increments('id');
            $table->integer("package_id")->unsigned();
            $table->timestamps();
            
            $table->foreign('package_id')->references('id')->on('package')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        Schema::dropIfExists('subscription_alerts');
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }
}
