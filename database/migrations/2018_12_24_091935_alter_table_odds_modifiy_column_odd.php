<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableOddsModifiyColumnOdd extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('ALTER TABLE `odd` MODIFY COLUMN `odd` DECIMAL(3, 2)');
        DB::statement('ALTER TABLE `distribution` MODIFY COLUMN `odd` DECIMAL(3, 2)');
        DB::statement('ALTER TABLE `event` MODIFY COLUMN `odd` DECIMAL(3, 2)');
        DB::statement('ALTER TABLE `archive_home` MODIFY COLUMN `odd` DECIMAL(3, 2)');
        DB::statement('ALTER TABLE `archive_big` MODIFY COLUMN `odd` DECIMAL(3, 2)');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('odd', function ($table) {
            $table->float('odd')->change();
        });
        Schema::table('distribution', function ($table) {
            $table->float('odd')->change();
        });
        Schema::table('event', function ($table) {
            $table->float('odd')->change();
        });
    }
}
