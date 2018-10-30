<?php

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\User;

class UserTableSeeder extends Seeder {

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        // Add admins to aplication.
        User::firstOrCreate([
            'name'       => 'Admin One',
            'email'      => 'admin1@app.com',
            'password'   => sha1("84437088hx7vb1O")
        ]);
        User::firstOrCreate([
            'name'       => 'Admin Two',
            'email'      => 'admin2@app.com',
            'password'   => sha1("6yXFGORMEptBtIt")
        ]);
        User::firstOrCreate([
            'name'       => 'Cristian Bucur',
            'email'      => 'cristian_bucur@app.com',
            'password'   => sha1("bDUHHHYnG30z5gj")
        ]);
        User::firstOrCreate([
            'name'       => 'ITManiax',
            'email'      => 'itmaniax@testing.com',
            'password'   => sha1("8xE2T0L34n9ZcRr")
        ]);
    }
}
