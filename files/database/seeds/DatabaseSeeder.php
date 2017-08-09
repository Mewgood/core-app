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
        $this->call('PredictionTableSeeder');
        $this->call('EventTableSeeder');
        $this->call('SiteTableSeeder');
        $this->call('PackageTableSeeder');
        $this->call('PackagePredictionTableSeeder');
        $this->call('SitePredictionTableSeeder');
        $this->call('SitePackageTableSeeder');
        $this->call('SiteResultStatusTableSeeder');
    }
}
