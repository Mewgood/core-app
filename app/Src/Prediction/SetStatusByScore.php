<?php

namespace App\Src\Prediction;

class SetStatusByScore
{
    private $score = '';
    private $homeTeam = 0;
    private $awayTeam = 0;
    private $predictionIdentifier = '';
    private $status = -1;

    private $errors = [];

    public function __construct(string $score, string $predictionIdentifier)
    {
        $this->score = $score;
        $this->predictionIdentifier = $predictionIdentifier;
    }

    public function evaluateStatus()
    {
        $score = explode('-', $this->score);
        $this->homeTeam = isset($score[0]) ? $score[0] : null;
        $this->awayTeam = isset($score[1]) ? $score[1] : null;

        if (!ctype_digit($this->homeTeam) || !ctype_digit($this->awayTeam) || empty($this->predictionIdentifier)) {
            $this->errors[] = "Invalid score or empty prediction identifier";
            return false;
        }

        // 1x2
        if ($this->predictionIdentifier == 'team1')
            $this->status = ($this->homeTeam > $this->awayTeam) ? 1 : 2;

        if ($this->predictionIdentifier == 'equal')
            $this->status = ($this->homeTeam === $this->awayTeam) ? 1 : 2;

        if ($this->predictionIdentifier == 'team2')
            $this->status = ($this->homeTeam < $this->awayTeam) ? 1 : 2;

        // to score
        if ($this->predictionIdentifier == 'noGoal')
            $this->status = ($this->homeTeam == 0 || $this->awayTeam == 0) ? 1 : 2;

        if ($this->predictionIdentifier == 'bothToScore')
            $this->status = ($this->homeTeam >  0 && $this->awayTeam > 0) ? 1 : 2;

        // over / under
        if ($this->predictionIdentifier == 'over_1.5')
            $this->status = ($this->homeTeam + $this->awayTeam) > 1.5 ? 1 : 2;

        if ($this->predictionIdentifier == 'over_2.5')
            $this->status = ($this->homeTeam + $this->awayTeam) > 2.5 ? 1 : 2;

        if ($this->predictionIdentifier == 'over_3.5')
            $this->status = ($this->homeTeam + $this->awayTeam) > 3.5 ? 1 : 2;

        if ($this->predictionIdentifier == 'under_1.5')
            $this->status = ($this->homeTeam + $this->awayTeam) < 1.5 ? 1 : 2;

        if ($this->predictionIdentifier == 'under_2.5')
            $this->status = ($this->homeTeam + $this->awayTeam) < 2.5 ? 1 : 2;

        if ($this->predictionIdentifier == 'under_3.5')
            $this->status = ($this->homeTeam + $this->awayTeam) < 3.5 ? 1 : 2;

        // over /under 1, 2, 3
        if ($this->predictionIdentifier == 'over_1') {
            $total = $this->homeTeam + $this->awayTeam;
            if ($total == 1)
                $this->status = 3;
            elseif ($total > 1)
                $this->status = 1;
            else
                $this->status = 2;
        }
        if ($this->predictionIdentifier == 'over_2') {
            $total = $this->homeTeam + $this->awayTeam;
            if ($total == 2)
                $this->status = 3;
            elseif ($total > 2)
                $this->status = 1;
            else
                $this->status = 2;
        }
        if ($this->predictionIdentifier == 'over_3') {
            $total = $this->homeTeam + $this->awayTeam;
            if ($total == 3)
                $this->status = 3;
            elseif ($total > 3)
                $this->status = 1;
            else
                $this->status = 2;
        }

        if ($this->predictionIdentifier == 'under_1') {
            $total = $this->homeTeam + $this->awayTeam;
            if ($total == 1)
                $this->status = 3;
            elseif ($total > 1)
                $this->status = 2;
            else
                $this->status = 1;
        }
        if ($this->predictionIdentifier == 'under_2') {
            $total = $this->homeTeam + $this->awayTeam;
            if ($total == 2)
                $this->status = 3;
            elseif ($total > 2)
                $this->status = 2;
            else
                $this->status = 1;
        }
        if ($this->predictionIdentifier == 'under_3') {
            $total = $this->homeTeam + $this->awayTeam;
            if ($total == 3)
                $this->status = 3;
            elseif ($total > 3)
                $this->status = 2;
            else
                $this->status = 1;
        }

        // ah
        if ($this->predictionIdentifier == 'team1-ah_+0.5') {
            $this->homeTeam += 0.5;
            $this->status = ($this->homeTeam > $this->awayTeam) ? 1 : 2;
        }
        if ($this->predictionIdentifier == 'team1-ah_+1.5') {
            $this->homeTeam += 1.5;
            $this->status = ($this->homeTeam > $this->awayTeam) ? 1 : 2;
        }
        if ($this->predictionIdentifier == 'team1-ah_+2.5') {
            $this->homeTeam += 2.5;
            $this->status = ($this->homeTeam > $this->awayTeam) ? 1 : 2;
        }
        if ($this->predictionIdentifier == 'team1-ah_+3.5') {
            $this->homeTeam += 3.5;
            $this->status = ($this->homeTeam > $this->awayTeam) ? 1 : 2;
        }

        if ($this->predictionIdentifier == 'team2-ah_+0.5') {
            $this->awayTeam += 0.5;
            $this->status = ($this->homeTeam < $this->awayTeam) ? 1 : 2;
        }
        if ($this->predictionIdentifier == 'team2-ah_+1.5') {
            $this->awayTeam += 1.5;
            $this->status = ($this->homeTeam < $this->awayTeam) ? 1 : 2;
        }
        if ($this->predictionIdentifier == 'team2-ah_+2.5') {
            $this->awayTeam += 2.5;
            $this->status = ($this->homeTeam < $this->awayTeam) ? 1 : 2;
        }
        if ($this->predictionIdentifier == 'team2-ah_+3.5') {
            $this->awayTeam += 3.5;
            $this->status = ($this->homeTeam < $this->awayTeam) ? 1 : 2;
        }

        if ($this->predictionIdentifier == 'team1-ah_-0.5') {
            $this->homeTeam -= 0.5;
            $this->status = ($this->homeTeam > $this->awayTeam) ? 1 : 2;
        }
        if ($this->predictionIdentifier == 'team1-ah_-1.5') {
            $this->homeTeam -= 1.5;
            $this->status = ($this->homeTeam > $this->awayTeam) ? 1 : 2;
        }
        if ($this->predictionIdentifier == 'team1-ah_-2.5') {
            $this->homeTeam -= 2.5;
            $this->status = ($this->homeTeam > $this->awayTeam) ? 1 : 2;
        }
        if ($this->predictionIdentifier == 'team1-ah_-3.5') {
            $this->homeTeam -= 3.5;
            $this->status = ($this->homeTeam > $this->awayTeam) ? 1 : 2;
        }

        if ($this->predictionIdentifier == 'team2-ah_-0.5') {
            $this->awayTeam -= 0.5;
            $this->status = ($this->homeTeam < $this->awayTeam) ? 1 : 2;
        }
        if ($this->predictionIdentifier == 'team2-ah_-1.5') {
            $this->awayTeam -= 1.5;
            $this->status = ($this->homeTeam < $this->awayTeam) ? 1 : 2;
        }
        if ($this->predictionIdentifier == 'team2-ah_-2.5') {
            $this->awayTeam -= 2.5;
            $this->status = ($this->homeTeam < $this->awayTeam) ? 1 : 2;
        }
        if ($this->predictionIdentifier == 'team2-ah_-3.5') {
            $this->awayTeam -= 3.5;
            $this->status = ($this->homeTeam < $this->awayTeam) ? 1 : 2;
        }


        // ah 1, 2, 3
        if ($this->predictionIdentifier == 'team1-ah_0') {
            if ($this->homeTeam == $this->awayTeam)
                $this->status = 3;
            elseif ($this->homeTeam > $this->awayTeam)
                $this->status = 1;
            else
                $this->status = 2;
        }

        if ($this->predictionIdentifier == 'team2-ah_0') {
            if ($this->homeTeam == $this->awayTeam)
                $this->status = 3;
            elseif ($this->homeTeam < $this->awayTeam)
                $this->status = 1;
            else
                $this->status = 2;
        }

        if ($this->predictionIdentifier == 'team1-ah_+1') {
            $this->homeTeam += 1;
            if ($this->homeTeam == $this->awayTeam)
                $this->status = 3;
            elseif ($this->homeTeam > $this->awayTeam)
                $this->status = 1;
            else
                $this->status = 2;
        }
        if ($this->predictionIdentifier == 'team1-ah_+2') {
            $this->homeTeam += 2;
            if ($this->homeTeam == $this->awayTeam)
                $this->status = 3;
            elseif ($this->homeTeam > $this->awayTeam)
                $this->status = 1;
            else
                $this->status = 2;
        }
        if ($this->predictionIdentifier == 'team1-ah_+3') {
            $this->homeTeam += 3;
            if ($this->homeTeam == $this->awayTeam)
                $this->status = 3;
            elseif ($this->homeTeam > $this->awayTeam)
                $this->status = 1;
            else
                $this->status = 2;
        }

        if ($this->predictionIdentifier == 'team1-ah_-1') {
            $this->homeTeam -= 1;
            if ($this->homeTeam == $this->awayTeam)
                $this->status = 3;
            elseif ($this->homeTeam > $this->awayTeam)
                $this->status = 1;
            else
                $this->status = 2;
        }
        if ($this->predictionIdentifier == 'team1-ah_-2') {
            $this->homeTeam -= 2;
            if ($this->homeTeam == $this->awayTeam)
                $this->status = 3;
            elseif ($this->homeTeam > $this->awayTeam)
                $this->status = 1;
            else
                $this->status = 2;
        }
        if ($this->predictionIdentifier == 'team1-ah_-3') {
            $this->homeTeam -= 3;
            if ($this->homeTeam == $this->awayTeam)
                $this->status = 3;
            elseif ($this->homeTeam > $this->awayTeam)
                $this->status = 1;
            else
                $this->status = 2;
        }

        if ($this->predictionIdentifier == 'team2-ah_+1') {
            $this->awayTeam += 1;
            if ($this->homeTeam == $this->awayTeam)
                $this->status = 3;
            elseif ($this->homeTeam < $this->awayTeam)
                $this->status = 1;
            else
                $this->status = 2;
        }
        if ($this->predictionIdentifier == 'team2-ah_+2') {
            $this->awayTeam += 2;
            if ($this->homeTeam == $this->awayTeam)
                $this->status = 3;
            elseif ($this->homeTeam < $this->awayTeam)
                $this->status = 1;
            else
                $this->status = 2;
        }
        if ($this->predictionIdentifier == 'team2-ah_+3') {
            $this->awayTeam += 3;
            if ($this->homeTeam == $this->awayTeam)
                $this->status = 3;
            elseif ($this->homeTeam < $this->awayTeam)
                $this->status = 1;
            else
                $this->status = 2;
        }

        if ($this->predictionIdentifier == 'team2-ah_-1') {
            $this->awayTeam -= 1;
            if ($this->homeTeam == $this->awayTeam)
                $this->status = 3;
            elseif ($this->homeTeam < $this->awayTeam)
                $this->status = 1;
            else
                $this->status = 2;
        }
        if ($this->predictionIdentifier == 'team2-ah_-2') {
            $this->awayTeam -= 2;
            if ($this->homeTeam == $this->awayTeam)
                $this->status = 3;
            elseif ($this->homeTeam < $this->awayTeam)
                $this->status = 1;
            else
                $this->status = 2;
        }
        if ($this->predictionIdentifier == 'team2-ah_-3') {
            $this->awayTeam -= 3;
            if ($this->homeTeam == $this->awayTeam)
                $this->status = 3;
            elseif ($this->homeTeam < $this->awayTeam)
                $this->status = 1;
            else
                $this->status = 2;
        }

    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getStatus() :int
    {
        return $this->status;
    }
}
