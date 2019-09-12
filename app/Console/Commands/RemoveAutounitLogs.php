<?php 

namespace App\Console\Commands;

class RemoveAutounitLogs extends CronCommand
{
    protected $name = 'logs:remove-autounit';

    public function fire()
    {
        $yesterday = date("Y-m-d", strtotime("-1 day", time()));
        $logFiles = array_diff(scandir(storage_path() . "/logs"), array('..', '.'));

        foreach ($logFiles as $logFile) {
            $index = strpos($logFile, "_");

            if ($index !== false) {
                $fileDate = substr($logFile, 0, $index);
    
                if (strtotime($fileDate) < strtotime($yesterday)) {
                    unlink(storage_path() . "/logs/" . $logFile);
                    echo "Removed " . storage_path() . "/logs/" . $logFile;
                }
            }
        }
    }
}