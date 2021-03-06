<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAutoUnitMonthlySettingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('auto_unit_monthly_setting', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('siteId')->index();
            $table->string('date')->index();
            $table->string('tipIdentifier')->index();
            $table->string('tableIdentifier')->index();
            $table->string('minOdd');
            $table->string('maxOdd');
            $table->integer('win');
            $table->integer('loss');
            $table->integer('draw');
            $table->string('prediction1x2');
            $table->string('predictionOU');
            $table->string('predictionAH');
            $table->string('predictionGG');
            $table->string('winrate');
            $table->string('configType');
            $table->integer('tipsPerDay');
            $table->integer('tipsNumber');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('auto_unit_monthly_setting');
    }
}
