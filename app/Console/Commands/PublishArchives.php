<?php 

namespace App\Console\Commands;

use Illuminate\Support\Facades\Log;

class PublishArchives extends CronCommand
{
    protected $name = 'publish:archives';
    protected $description = 'Publish archives in sites';

    public function fire()
    {
        //$cron = $this->startCron();

        $info = [
            'published' => 0,
            'notPublished' => 0,
        ];

        $data = [];

        $maxDate = gmdate('Y-m-d H:i:s', time() - 60 * 60);
        $archives = \App\ArchivePublishStatus::where('created_at', '>', $maxDate)
            ->limit(10)->get();

        $actionInstance = new \App\Http\Controllers\Admin\Client\TriggerAction();

        foreach ($archives as $archive) {

            if ($archive->type == 'archiveHome')
                $wasPublish = $actionInstance->updateArchiveHome($archive->siteId);
            else
                $wasPublish = $actionInstance->updateArchiveBig($archive->siteId);

            if ($wasPublish['type'] == 'success') {
                $info['published']++;
                continue;
            }

            $info['notPublished']++;
            $data[] = $wasPublish;
            $info['errors'][] = $wasPublish['message'];
        }

        $this->info(json_encode([
            "data" => $data,
            "info" => $info, 
            "message" => "Archive Home sent",
            "date" => gmdate('Y-m-d H:i:s')
        ]));
        //$this->stopCron($cron, $info);
        return true;
    }
}

