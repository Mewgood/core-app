<?php

namespace App\Src\AutoUnit;

Class Schedule
{
    private $settings = null;
    private $schedule = [];
    private $numberOfDays = 0;
    private $range = [];

    private $totalTips = 0;

    public function __construct(\App\Models\AutoUnit\MonthlySetting $settings)
    {
        $this->settings = $settings;
        /* print_r($settings->toArray()); */
    }

    public function createSchedule() :void
    {
        // set range of each day for all month
        $this->setRangeOfDays();

        $predictions = [
            '1x2',
            'ah',
            'ou',
            'gg',
        ];

        // days type
        if ($this->settings->configType == 'days') {

            $this->totalTips = $this->settings->tipsPerDay * count($this->range);

            if ($this->totalTips > 0) {

                $t = $this->totalTips;
                $p = $this->getPredictionsGroupNumber();

                for ($x = $this->settings->tipsPerDay; $x > 0; $x--) {

                    foreach ($this->range as $day) {

                        $predictionGroup = $this->getRandomPrediction($p);

                        if ($predictionGroup === null) {
                            continue;
                        }

                        $p[$predictionGroup]--;

                        $statusId = $this->getRandomStatus($predictionGroup);
                        if ($statusId === null)
                            continue;

                        $this->schedule[] = [
                            'siteId'          => $this->settings->siteId,
                            'date'            => $this->settings->date,
                            'tipIdentifier'   => $this->settings->tipIdentifier,
                            'tableIdentifier' => $this->settings->tableIdentifier,
                            'predictionGroup' => $predictionGroup,
                            'statusId'        => $statusId,
                            'status'          => 'waiting',
                            'info'            => json_encode([]),
                            'systemDate'      => $day,
                        ];
                    }
                }
            }
        }

        if ($this->settings->configType == 'tips') {

            $this->totalTips = $this->settings->tipsNumber;

            $daysNumber = count($this->range);
            $totalTips = $this->settings->win + $this->settings->loss + $this->settings->draw;

            if ($totalTips < 1)
                return;

            $multiply = ceil($totalTips / $daysNumber);

            $t = $this->totalTips;
            $p = $this->getPredictionsGroupNumber();

            for ($x = 0; $x < $multiply; $x++) {
                $p = $this->addTipsRandomEvents($p);
            }

            /* print_r($this->range); */
            /* print_r($this->settings->toArray()); */
        }
    }

    public function addTipsRandomEvents(array $p)
    {

        /* error_reporting(E_ALL); */
        /* ini_set('display_errors', 1); */
        foreach ($this->range as $day) {

            $predictionGroup = $this->getRandomPrediction($p);

            if ($predictionGroup === null)
                continue;

            $p[$predictionGroup]--;

            $statusId = $this->getRandomStatus($predictionGroup);
            if ($statusId === null)
                continue;

            $this->schedule[] = [
                'siteId'          => $this->settings->siteId,
                'date'            => $this->settings->date,
                'tipIdentifier'   => $this->settings->tipIdentifier,
                'tableIdentifier' => $this->settings->tableIdentifier,
                'predictionGroup' => $predictionGroup,
                'statusId'        => $statusId,
                'status'          => 'waiting',
                'info'            => json_encode([]),
                'systemDate'      => $this->range[rand(0, count($this->range) -1)],
            ];
        }

        return $p;
    }

    private function getPredictionsGroupNumber() :array
    {
        $p = [
            '1x2' => 0,
            'O/U'  => 0,
            'AH'  => 0,
            'G/G'  => 0,
        ];

        $exit = false;
        while ($exit == false) {

            $_1x2 = $this->incrementEventsNumber($p, '1x2');
            if ($_1x2)
                $p['1x2']++;

            $_ah = $this->incrementEventsNumber($p, 'AH');
            if ($_ah)
                $p['AH']++;

            $_ou = $this->incrementEventsNumber($p, 'O/U');
            if ($_ou)
                $p['O/U']++;

            $_gg = $this->incrementEventsNumber($p, 'G/G');
            if ($_gg)
                $p['G/G']++;

            if ($_1x2 == false && $_ah == false && $_ou == false && $_gg == false)
                $exit = true;
        }

        return $p;
    }

    private function incrementEventsNumber(array $p, string $type)
    {
        $identifier = 'prediction' . $type;

        if ($this->settings->{$identifier} < 1)
            return false;

        if ($p[$type] >= round(($this->totalTips * $this->settings->{$identifier}) / 100))
            return false;

        return true;
    }

    private function getRandomStatus($predictionGroup)
    {
        $w = $this->settings->win > 0 ? $this->settings->win : 1;
        $l = $this->settings->loss > 0 ? $this->settings->loss : 1;
        $d = $this->settings->draw > 0 ? $this->settings->draw : 1;

        $wp = round(($w * 100) / ($w + $l + $d));
        $lp = round(($w * 100) / ($w + $l + $d));
        $dp = round(($w * 100) / ($w + $l + $d));

        for ($i = 0; $i < 1000; $i++) {

            $rand = rand(1,99);
            $statusId = ($rand < $wp) ? 1 : 2;

            // for draw
            if ($predictionGroup == 'AH' || $predictionGroup == 'O/U')
               $statusId = rand(1,3);

            if ($statusId == 1) {
                if ($this->settings->win > 0) {
                    $this->settings->win--;
                    return $statusId;
                }
            }

            if ($statusId == 2) {
                if ($this->settings->loss > 0) {
                    $this->settings->loss--;
                    return $statusId;
                }
            }

            if ($statusId == 3) {
                if ($this->settings->draw > 0) {
                    $this->settings->draw--;
                    return $statusId;
                }
            }
        }

        return null;
    }

    private function getRandomPrediction($p)
    {
        $v = [
            0 => '1x2',
            1 => 'O/U',
            2 => 'AH',
            3 => 'G/G'
        ];

        for ($i = 0; $i < 1000; $i++) {

            $prediction = $v[rand(0, 3)];

            if ($p[$prediction] > 0)
                return $prediction;
        }

        return null;
    }

    private function setRangeOfDays() : void
    {
        $stringDate = gmdate("Y-m-d");

        $startDate = new \DateTime($stringDate);
        $endDate = new \DateTime($startDate->format('Y-m-t'));
        $endDate->modify('+1 day');

        $interval = new \DateInterval('P1D');
        $dateRange = new \DatePeriod($startDate, $interval, $endDate);

        foreach ($dateRange as $date) {
            $this->range[] = $date->format('Y-m-d');
        }
    }

    public function getSchedule() :array
    {
        return $this->schedule;
    }
}
