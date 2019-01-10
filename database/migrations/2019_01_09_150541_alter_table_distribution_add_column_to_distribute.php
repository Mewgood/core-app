<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableDistributionAddColumnToDistribute extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('distribution', function (Blueprint $table) {
            $table->boolean('to_distribute')->default(true);
        });
        Schema::table('association', function (Blueprint $table) {
            $table->boolean('to_distribute')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('distribution', function (Blueprint $table) {
            $table->dropColumn('to_distribute');
        });
        Schema::table('association', function (Blueprint $table) {
            $table->dropColumn('to_distribute');
        });
    }
}
