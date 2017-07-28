<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->call('EventTableSeeder');
        $this->call('SiteTableSeeder');
        $this->call('PredictionTableSeeder');
        $this->call('PackageTableSeeder');
        $this->call('PackagePredictionGroupTableSeeder');
    }
}
