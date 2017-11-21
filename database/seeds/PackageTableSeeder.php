<?php

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\Package;

class PackageTableSeeder extends Seeder {

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $sites = [
            1 => [
                'normal_1' => [
                    'name'             => '3 Tips',
                    'identifier'       => 'normal_1',
                    'tipIdentifier'    => 'tip_1',
                    'tableIdentifier'  => 'table_1',
                    'vipFlag'          => '',
                    'isVip'            => false,
                    'isRecurring'      => false,
                    'subscriptionType' => 'tips',
                    'subscription'     => 3,
                    'tipsPerDay'       => 1,
                    'price'            => 69,
                    'fromName'         => 'From name',
                    'subject'          => 'subject',
                    'template'         => '<p><font face="Times New Roman">{{section TIP}}</font></p><p><font face="Times New Roman">Welcome {{email}}</font></p><p><font face="Times New Roman">{{events}}</font></p><p><font face="Times New Roman">{{eventDate}} &nbsp;{{country}}: {{league}}<br>{{homeTeam}} - {{awayTeam}}</font></p><p><font face="Times New Roman">Bet: {{predictionName}}</font></p><p><font face="Times New Roman">{{/events}}</font></p><p><font face="Times New Roman">{{/section TIP}}<br>{{section NO TIP}}</font></p><p><font face="Times New Roman">Welcome {{email}}</font></p><p><font face="Times New Roman">There is no profitable tip for today, so will wait till tomorow.</font></p><p><font face="Times New Roman">{{/section NO TIP}}</font></p><p><span style="font-family: &quot;Times New Roman&quot;;">Have a good day!</span><br style="font-family: &quot;Times New Roman&quot;;"><span style="font-family: &quot;Times New Roman&quot;;">\'\' `` "" ----------- ___ \\?????////////</span><font face="Times New Roman"><br></font></p>1"`',
                ],
                'normal_2' => [
                    'name'             => '30 Tips',
                    'identifier'       => 'normal_2',
                    'tipIdentifier'    => 'tip_1',
                    'tableIdentifier'  => 'table_1',
                    'vipFlag'          => '',
                    'isVip'            => false,
                    'isRecurring'      => false,
                    'subscriptionType' => 'tips',
                    'subscription'     => 30,
                    'tipsPerDay'       => 1,
                    'price'            => 399,
                    'fromName'         => 'From Name',
                    'subject'          => 'subject',
                    'template'         => '<p><font face="Times New Roman">{{section TIP}}</font></p><p><font face="Times New Roman">Welcome {{email}}</font></p><p><font face="Times New Roman">{{events}}</font></p><p><font face="Times New Roman">{{eventDate}} &nbsp;{{country}}: {{league}}<br>{{homeTeam}} - {{awayTeam}}</font></p><p><font face="Times New Roman">Bet: {{predictionName}}</font></p><p><font face="Times New Roman">{{/events}}</font></p><p><font face="Times New Roman">{{/section TIP}}<br>{{section NO TIP}}</font></p><p><font face="Times New Roman">Welcome {{email}}</font></p><p><font face="Times New Roman">There is no profitable tip for today, so will wait till tomorow.</font></p><p><font face="Times New Roman">{{/section NO TIP}}</font></p><p><span style="font-family: &quot;Times New Roman&quot;;">Have a good day!</span><br style="font-family: &quot;Times New Roman&quot;;"><span style="font-family: &quot;Times New Roman&quot;;">\'\' `` "" ----------- ___ \\?????////////</span><font face="Times New Roman"><br></font></p>1"`',
                ],
                'vip_1' => [
                    'name'             => '2 Vip Tips',
                    'identifier'       => 'vip_1',
                    'tipIdentifier'    => 'tip_2',
                    'tableIdentifier'  => 'table_2',
                    'vipFlag'          => ' !VIP!',
                    'isVip'            => true,
                    'isRecurring'      => false,
                    'subscriptionType' => 'tips',
                    'subscription'     => 2,
                    'tipsPerDay'       => 1,
                    'price'            => 650,
                    'fromName'         => 'From Name',
                    'subject'          => 'subject',
                    'template'         => '<p><font face="Times New Roman">{{section TIP}}</font></p><p><font face="Times New Roman">Welcome {{email}}</font></p><p><font face="Times New Roman">{{events}}</font></p><p><font face="Times New Roman">{{eventDate}} &nbsp;{{country}}: {{league}}<br>{{homeTeam}} - {{awayTeam}}</font></p><p><font face="Times New Roman">Bet: {{predictionName}}</font></p><p><font face="Times New Roman">{{/events}}</font></p><p><font face="Times New Roman">{{/section TIP}}<br>{{section NO TIP}}</font></p><p><font face="Times New Roman">Welcome {{email}}</font></p><p><font face="Times New Roman">There is no profitable tip for today, so will wait till tomorow.</font></p><p><font face="Times New Roman">{{/section NO TIP}}</font></p><p><span style="font-family: &quot;Times New Roman&quot;;">Have a good day!</span><br style="font-family: &quot;Times New Roman&quot;;"><span style="font-family: &quot;Times New Roman&quot;;">\'\' `` "" ----------- ___ \\?????////////</span><font face="Times New Roman"><br></font></p>1"`',
                ],
            ],
            2 => [
                'normal_1' => [
                    'name'             => '10 Tips',
                    'identifier'       => 'normal_1',
                    'tipIdentifier'    => 'tip_1',
                    'tableIdentifier'  => 'table_1',
                    'vipFlag'          => '',
                    'isVip'            => false,
                    'isRecurring'      => false,
                    'subscriptionType' => 'tips',
                    'subscription'     => 10,
                    'tipsPerDay'       => 2,
                    'price'            => 120,
                    'fromName'         => 'From Name',
                    'subject'          => 'subject',
                    'template'         => '<p><font face="Times New Roman">{{section TIP}}</font></p><p><font face="Times New Roman">Welcome {{email}}</font></p><p><font face="Times New Roman">{{events}}</font></p><p><font face="Times New Roman">{{eventDate}} &nbsp;{{country}}: {{league}}<br>{{homeTeam}} - {{awayTeam}}</font></p><p><font face="Times New Roman">Bet: {{predictionName}}</font></p><p><font face="Times New Roman">{{/events}}</font></p><p><font face="Times New Roman">{{/section TIP}}<br>{{section NO TIP}}</font></p><p><font face="Times New Roman">Welcome {{email}}</font></p><p><font face="Times New Roman">There is no profitable tip for today, so will wait till tomorow.</font></p><p><font face="Times New Roman">{{/section NO TIP}}</font></p><p><span style="font-family: &quot;Times New Roman&quot;;">Have a good day!</span><br style="font-family: &quot;Times New Roman&quot;;"><span style="font-family: &quot;Times New Roman&quot;;">\'\' `` "" ----------- ___ \\?????////////</span><font face="Times New Roman"><br></font></p>1"`',
                ],
                'normal_2' => [
                    'name'             => '40 Tips',
                    'identifier'       => 'normal_2',
                    'tipIdentifier'    => 'tip_1',
                    'tableIdentifier'  => 'table_1',
                    'vipFlag'          => '',
                    'isVip'            => false,
                    'isRecurring'      => false,
                    'subscriptionType' => 'tips',
                    'subscription'     => 40,
                    'tipsPerDay'       => 2,
                    'price'            => 300,
                    'fromName'         => 'From Name',
                    'subject'          => 'subject',
                    'template'         => '<p><font face="Times New Roman">{{section TIP}}</font></p><p><font face="Times New Roman">Welcome {{email}}</font></p><p><font face="Times New Roman">{{events}}</font></p><p><font face="Times New Roman">{{eventDate}} &nbsp;{{country}}: {{league}}<br>{{homeTeam}} - {{awayTeam}}</font></p><p><font face="Times New Roman">Bet: {{predictionName}}</font></p><p><font face="Times New Roman">{{/events}}</font></p><p><font face="Times New Roman">{{/section TIP}}<br>{{section NO TIP}}</font></p><p><font face="Times New Roman">Welcome {{email}}</font></p><p><font face="Times New Roman">There is no profitable tip for today, so will wait till tomorow.</font></p><p><font face="Times New Roman">{{/section NO TIP}}</font></p><p><span style="font-family: &quot;Times New Roman&quot;;">Have a good day!</span><br style="font-family: &quot;Times New Roman&quot;;"><span style="font-family: &quot;Times New Roman&quot;;">\'\' `` "" ----------- ___ \\?????////////</span><font face="Times New Roman"><br></font></p>1"`',
                ],
            ],
            3 => [
                'normal_1' => [
                    'name'             => 'Eugene Hendrix',
                    'identifier'       => 'normal_1',
                    'tipIdentifier'    => 'tip_1',
                    'tableIdentifier'  => 'table_1',
                    'vipFlag'          => '',
                    'isVip'            => false,
                    'isRecurring'      => true,
                    'subscriptionType' => 'days',
                    'subscription'     => 30,
                    'tipsPerDay'       => 1,
                    'price'            => 299,
                    'fromName'         => 'From Name',
                    'subject'          => 'subject',
                    'template'         => '<p><font face="Times New Roman">{{section TIP}}</font></p><p><font face="Times New Roman">Welcome {{email}}</font></p><p><font face="Times New Roman">{{events}}</font></p><p><font face="Times New Roman">{{eventDate}} &nbsp;{{country}}: {{league}}<br>{{homeTeam}} - {{awayTeam}}</font></p><p><font face="Times New Roman">Bet: {{predictionName}}</font></p><p><font face="Times New Roman">{{/events}}</font></p><p><font face="Times New Roman">{{/section TIP}}<br>{{section NO TIP}}</font></p><p><font face="Times New Roman">Welcome {{email}}</font></p><p><font face="Times New Roman">There is no profitable tip for today, so will wait till tomorow.</font></p><p><font face="Times New Roman">{{/section NO TIP}}</font></p><p><span style="font-family: &quot;Times New Roman&quot;;">Have a good day!</span><br style="font-family: &quot;Times New Roman&quot;;"><span style="font-family: &quot;Times New Roman&quot;;">\'\' `` "" ----------- ___ \\?????////////</span><font face="Times New Roman"><br></font></p>1"`',
                ],
                'normal_2' => [
                    'name'             => 'Marcus Spiros',
                    'identifier'       => 'normal_2',
                    'tipIdentifier'    => 'tip_2',
                    'tableIdentifier'  => 'table_2',
                    'vipFlag'          => '--VIP--',
                    'isVip'            => false,
                    'isRecurring'      => true,
                    'subscriptionType' => 'days',
                    'subscription'     => 30,
                    'tipsPerDay'       => 1,
                    'price'            => 299,
                    'fromName'         => 'From Name',
                    'subject'          => 'subject',
                    'template'         => '<p><font face="Times New Roman">{{section TIP}}</font></p><p><font face="Times New Roman">Welcome {{email}}</font></p><p><font face="Times New Roman">{{events}}</font></p><p><font face="Times New Roman">{{eventDate}} &nbsp;{{country}}: {{league}}<br>{{homeTeam}} - {{awayTeam}}</font></p><p><font face="Times New Roman">Bet: {{predictionName}}</font></p><p><font face="Times New Roman">{{/events}}</font></p><p><font face="Times New Roman">{{/section TIP}}<br>{{section NO TIP}}</font></p><p><font face="Times New Roman">Welcome {{email}}</font></p><p><font face="Times New Roman">There is no profitable tip for today, so will wait till tomorow.</font></p><p><font face="Times New Roman">{{/section NO TIP}}</font></p><p><span style="font-family: &quot;Times New Roman&quot;;">Have a good day!</span><br style="font-family: &quot;Times New Roman&quot;;"><span style="font-family: &quot;Times New Roman&quot;;">\'\' `` "" ----------- ___ \\?????////////</span><font face="Times New Roman"><br></font></p>1"`',
                ],
                'vip_1' => [
                    'name'             => 'Quentin Whatt -- VIP --',
                    'identifier'       => 'normal_3',
                    'tipIdentifier'    => 'tip_3',
                    'tableIdentifier'  => 'table_3',
                    'vipFlag'          => '',
                    'isVip'            => true,
                    'isRecurring'      => true,
                    'subscriptionType' => 'days',
                    'subscription'     => 30,
                    'tipsPerDay'       => 1,
                    'price'            => 699,
                    'fromName'         => 'From Name',
                    'subject'          => 'subject',
                    'template'         => '<p><font face="Times New Roman">{{section TIP}}</font></p><p><font face="Times New Roman">Welcome {{email}}</font></p><p><font face="Times New Roman">{{events}}</font></p><p><font face="Times New Roman">{{eventDate}} &nbsp;{{country}}: {{league}}<br>{{homeTeam}} - {{awayTeam}}</font></p><p><font face="Times New Roman">Bet: {{predictionName}}</font></p><p><font face="Times New Roman">{{/events}}</font></p><p><font face="Times New Roman">{{/section TIP}}<br>{{section NO TIP}}</font></p><p><font face="Times New Roman">Welcome {{email}}</font></p><p><font face="Times New Roman">There is no profitable tip for today, so will wait till tomorow.</font></p><p><font face="Times New Roman">{{/section NO TIP}}</font></p><p><span style="font-family: &quot;Times New Roman&quot;;">Have a good day!</span><br style="font-family: &quot;Times New Roman&quot;;"><span style="font-family: &quot;Times New Roman&quot;;">\'\' `` "" ----------- ___ \\?????////////</span><font face="Times New Roman"><br></font></p>1"`',
                ],
            ],
            4 => [
                'normal_1' => [
                    'name'             => '1 Month accesss',
                    'identifier'       => 'normal_1',
                    'tipIdentifier'    => 'tip_1',
                    'tableIdentifier'  => 'table_1',
                    'vipFlag'          => '',
                    'isVip'            => false,
                    'isRecurring'      => true,
                    'subscriptionType' => 'days',
                    'subscription'     => 30,
                    'tipsPerDay'       => 2,
                    'price'            => 349,
                    'fromName'         => 'From Name',
                    'subject'          => 'subject',
                    'template'         => '<p><font face="Times New Roman">{{section TIP}}</font></p><p><font face="Times New Roman">Welcome {{email}}</font></p><p><font face="Times New Roman">{{events}}</font></p><p><font face="Times New Roman">{{eventDate}} &nbsp;{{country}}: {{league}}<br>{{homeTeam}} - {{awayTeam}}</font></p><p><font face="Times New Roman">Bet: {{predictionName}}</font></p><p><font face="Times New Roman">{{/events}}</font></p><p><font face="Times New Roman">{{/section TIP}}<br>{{section NO TIP}}</font></p><p><font face="Times New Roman">Welcome {{email}}</font></p><p><font face="Times New Roman">There is no profitable tip for today, so will wait till tomorow.</font></p><p><font face="Times New Roman">{{/section NO TIP}}</font></p><p><span style="font-family: &quot;Times New Roman&quot;;">Have a good day!</span><br style="font-family: &quot;Times New Roman&quot;;"><span style="font-family: &quot;Times New Roman&quot;;">\'\' `` "" ----------- ___ \\?????////////</span><font face="Times New Roman"><br></font></p>1"`',
                ],
            ],
            5 => [
                'normal_1' => [
                    'name'             => '3 Tips',
                    'identifier'       => 'normal_1',
                    'tipIdentifier'    => 'tip_1',
                    'tableIdentifier'  => 'table_1',
                    'vipFlag'          => '',
                    'isVip'            => false,
                    'isRecurring'      => false,
                    'subscriptionType' => 'tips',
                    'subscription'     => 3,
                    'tipsPerDay'       => 1,
                    'price'            => 99,
                    'fromName'         => 'From Name',
                    'subject'          => 'subject',
                    'template'         => '<p><font face="Times New Roman">{{section TIP}}</font></p><p><font face="Times New Roman">Welcome {{email}}</font></p><p><font face="Times New Roman">{{events}}</font></p><p><font face="Times New Roman">{{eventDate}} &nbsp;{{country}}: {{league}}<br>{{homeTeam}} - {{awayTeam}}</font></p><p><font face="Times New Roman">Bet: {{predictionName}}</font></p><p><font face="Times New Roman">{{/events}}</font></p><p><font face="Times New Roman">{{/section TIP}}<br>{{section NO TIP}}</font></p><p><font face="Times New Roman">Welcome {{email}}</font></p><p><font face="Times New Roman">There is no profitable tip for today, so will wait till tomorow.</font></p><p><font face="Times New Roman">{{/section NO TIP}}</font></p><p><span style="font-family: &quot;Times New Roman&quot;;">Have a good day!</span><br style="font-family: &quot;Times New Roman&quot;;"><span style="font-family: &quot;Times New Roman&quot;;">\'\' `` "" ----------- ___ \\?????////////</span><font face="Times New Roman"><br></font></p>1"`',
                ],
                'vip_1' => [
                    'name'             => '1 VIP TIP',
                    'identifier'       => 'vip_1',
                    'tipIdentifier'    => 'tip_2',
                    'tableIdentifier'  => 'table_1',
                    'vipFlag'          => '- Vip Tip -',
                    'isVip'            => true,
                    'isRecurring'      => false,
                    'subscriptionType' => 'tips',
                    'subscription'     => 1,
                    'tipsPerDay'       => 1,
                    'price'            => 750,
                    'fromName'         => 'From Name',
                    'subject'          => 'subject',
                    'template'         => '<p><font face="Times New Roman">{{section TIP}}</font></p><p><font face="Times New Roman">Welcome {{email}}</font></p><p><font face="Times New Roman">{{events}}</font></p><p><font face="Times New Roman">{{eventDate}} &nbsp;{{country}}: {{league}}<br>{{homeTeam}} - {{awayTeam}}</font></p><p><font face="Times New Roman">Bet: {{predictionName}}</font></p><p><font face="Times New Roman">{{/events}}</font></p><p><font face="Times New Roman">{{/section TIP}}<br>{{section NO TIP}}</font></p><p><font face="Times New Roman">Welcome {{email}}</font></p><p><font face="Times New Roman">There is no profitable tip for today, so will wait till tomorow.</font></p><p><font face="Times New Roman">{{/section NO TIP}}</font></p><p><span style="font-family: &quot;Times New Roman&quot;;">Have a good day!</span><br style="font-family: &quot;Times New Roman&quot;;"><span style="font-family: &quot;Times New Roman&quot;;">\'\' `` "" ----------- ___ \\?????////////</span><font face="Times New Roman"><br></font></p>1"`',
                ],
            ],
        ];

        foreach ($sites as $siteId => $site) {
            foreach ($site as $packeIdentifier => $pack) {
                Package::firstOrCreate([
                    'siteId'           => $siteId,
                    'name'             => $pack['name'],
                    'identifier'       => $pack['identifier'],
                    'tipIdentifier'    => $pack['tipIdentifier'],
                    'tableIdentifier'  => $pack['tableIdentifier'],
                    'vipFlag'          => $pack['vipFlag'],
                    'isVip'            => $pack['isVip'],
                    'isRecurring'      => $pack['isRecurring'],
                    'subscriptionType' => $pack['subscriptionType'],
                    'subscription'     => $pack['subscription'],
                    'tipsPerDay'       => $pack['tipsPerDay'],
                    'subject'          => $pack['subject'],
                    'price'            => $pack['price'],
                    'fromName'         => $pack['fromName'],
                    'template'         => $pack['template'],
                ]);
            }
        }
    }
}
