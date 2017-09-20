<?php

namespace App\Http\Controllers\Admin\Email;

use App\Http\Controllers\Controller;
use Carbon\Carbon;

// this function only validate if selected events
// - make part from same packages
// - there is only noTip or tip
// - all events have less then 5 minutes to start
// @param array $ids (ids of distributed events)
// @return $this
class ValidateGroup extends Controller
{
    public $error     = false;
    public $message  = '';
    public $packageId = 0;
    public $isNoTip   = '';

    private $ids;

    public function __construct($ids)
    {
        $this->ids = $ids;

        if (!$this->ids) {
            $this->error = true;
            $this->message .= "No Events Selected! \r\n";
            return $this;
        }

        // check if event make part fron same package
        foreach ($this->ids as $id) {

            $event = \App\Distribution::find($id);
            if (!$event) {
                $this->error = true;
                $this->message .= "Event with id $id not found. Maybe was deleted. \r\n";
                continue;
            }

            // set packageId in first loop
            if ($this->packageId === 0)
                $this->packageId = $event->packageId;

            // check if all events make part from same package
            if ((int)$event->packageId !== $this->packageId) {
                $this->error = true;
                $this->message .= "Preview and Send can use events form same package, You choose events from many packages. \r\n";
                continue;
            }

            // set type tip | noTip on first loop
            if ($this->isNoTip === '')
                $this->isNoTip = $event->isNoTip;

            // check if all events have same type tip | noTip
            if ($this->isNoTip !== (int)$event->isNoTip) {
                $this->error = true;
                $this->message .= "You can not select TIP and NO TIP at same time. \r\n";
                continue;
            }

            // do not check when event is start for noTip
            if ($this->isNoTip == 1)
                continue;

            // event must have more than 5 minutes to start.
            if ($event->eventDate < Carbon::now('UTC')->addMinutes(5)) {
                $this->error = true;
                $this->message .= "Event with id: $id will start in less than 5 minutes. You can not use this event. \r\n";
                continue;
            }
        }

        return $this;
    }

}
