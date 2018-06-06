<?php

namespace App\Http\Controllers\Admin\Email;

use App\Http\Controllers\Controller;

class ReplaceTipsInTemplate extends Controller
{
    public $template;

    private $isNoTip;
    private $events = [];
    private $tags = [
        'events' => [
            'from' => '{{events}}',
            'to'   => '{{/events}}',
        ],
    ];

    // @param string $template
    // @param array $events
    public function __construct($template, $events, $isNoTip)
    {
        $this->template = $template;
        $this->events   = $events;
        $this->isNoTip  = $isNoTip;

        $this->replaceTipsInTemplate();

        return $this;
    }

    // this function will replace placeholders with events info
    private function replaceTipsInTemplate()
    {

        // case NoTip
        if ($this->isNoTip == 1) {
            return;
        }

        $from = $this->tags['events']['from'];
        $to = $this->tags['events']['to'];

        $split = $this->splitString($this->template, $from, $to);
        $this->template = $split['header'];

        foreach ($this->events as $event) {

            // change team name in prediction
            if (strpos($event['predictionName'], '{{team1}}') !== false) {
                $event['predictionName'] = str_replace('{{team1}}', $event['homeTeam'], $event['predictionName']);
            }

            if (strpos($event['predictionName'], '{{team2}}') !== false)
                $event['predictionName'] = str_replace('{{team2}}', $event['awayTeam'], $event['predictionName']);

            $find = [
                '{{eventDate}}',
                '{{country}}',
                '{{league}}',
                '{{homeTeam}}',
                '{{awayTeam}}',
                '{{predictionName}}',
            ];
			
			// GDM : we're creating the date Obj in the Distribution Controller according to the site's date format
			// date('Y-m-d', strtotime($event['eventDate'])),
            $replace = [
                $event['eventDate'],
                $event['country'],
                $event['league'],
                $event['homeTeam'],
                $event['awayTeam'],
                $event['predictionName'],
            ];

            $this->template .= str_replace($find, $replace, $split['body']);
        }

        $this->template .= $split['footer'];
    }

    // this function will split a string in 3 sections
    // @param string $str
    // @param string $from
    // @param string $to
    // @return array()
    private function splitString($str, $from, $to)
    {
        $data = [
            'header' => '',
            'body'   => '',
            'footer' => '',
        ];

        $data['header'] = substr($str, 0, strpos($str, $from));
        $sub = substr($str, strpos($str, $from)+strlen($from),strlen($str));
        $data['body'] = substr($sub,0,strpos($sub,$to));
        $data['footer'] = substr($sub, strpos($sub, $to) + strlen($to), strlen($sub));

        return $data;
    }
}

