<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateArchiveHomeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('archive_home', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('distributionId')->unsigned();
            $table->integer('order')->unsigned();
            $table->integer('associationId')->uunsigned();
            $table->integer('eventId')->unsigned();
            $table->integer('siteId')->unsigned();
            $table->integer('packageId')->unsigned();
            $table->string('source');
            $table->string('provider');
            $table->string('tableIdentifier');
            $table->string('tipIdentifier');
            $table->integer('isVisible')->unsigned()->default(1);
            $table->integer('isNoTip')->unsigned()->default(0);
            $table->integer('isVip')->unsigned()->default(0);
            $table->string('country');
            $table->string('countryCode');
            $table->string('league');
            $table->integer('leagueId')->unsigned();
            $table->string('homeTeam');
            $table->integer('homeTeamId')->unsigned();
            $table->string('awayTeam');
            $table->integer('awayTeamId')->unsigned();
            $table->string('odd', 10);
            $table->string('predictionId');
            $table->string('predictionName');
            $table->string('result');
            $table->string('statusId', 2);
            $table->string('stringEventDate');
            $table->timestamp('eventDate')->nullable();
            $table->timestamp('mailingDate')->nullable();
            $table->timestamp('publishDate')->nullable();
            $table->string('systemDate')->nullable();
            $table->timestamps();
            $table->index(['distributionId', 'order', 'associationId', 'eventId', 'siteId', 'packageId', 'tableIdentifier', 'tipIdentifier', 'isVisible', 'systemDate']);
        });
    }

    /**
     * Reverse the migrations.
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('archive_home');
    }
}
