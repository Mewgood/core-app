<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\ArchiveHome;
use Illuminate\Support\Facades\DB;

class Archive extends Controller
{

    // publish events in archive
    // @param array $ids (distributionId)
    //  - mark events publish in distribution
    //  - send events in archive
    // @return array()
    public function publish($ids)
    {
        $alreadyPublish = 0;
        $inserted = 0;
        $notHaveResultOrStatus = 0;
        $sameTipNotPublished = 0;

        if (!$ids)
            return [
                "type" => "error",
                "message" => "No events provided!",
            ];
			
		// before publishing , we need to make sure the order is correct ( for a given site, we want the VIP distributions to have higher order than the NON-VIP ones )
		$distributions = \App\Distribution::whereIn('id', $ids)
            ->orderBy('eventDate','ASC')
            ->orderBy('isVip', 'DESC')
            ->get();
		$sorted_ids = [];		
		foreach($distributions as $distribution) {
			$sorted_ids[] = $distribution->id;
		}
        foreach ($sorted_ids as $id) {
            $distribution = \App\Distribution::where('id', $id)->first();

            // TODO check if distributed event exists

            if ($distribution->isPublish) {
                $alreadyPublish++;
                continue;
            }

            // noTip not have results and status
            if (!$distribution->isNoTip && !($distribution->statusId && $distribution->statusId == '4' ) ) {
                if (!$distribution->result || !$distribution->statusId) {
                    $notHaveResultOrStatus++;
                    continue;
                }
            }

            // for no tip set eventDate = systemDate
            if ($distribution->isNoTip)
                $distribution->eventDate = $distribution->systemDate;

            $distribution->isPublish = 1;
            if (! $distribution->publishTime)
                $distribution->publishTime = time();

            // update in distribution
            $distribution->save();

            // transform in array
            $distribution = json_decode(json_encode($distribution), true);

            // check if event was already published on another package with same tip
            if (\App\ArchiveBig::where('eventId', $distribution['eventId'])
                ->where('siteId', $distribution['siteId'])
                ->where('tableIdentifier', $distribution['tableIdentifier'])
                ->where('systemDate', $distribution['systemDate'])
                ->where('tipIdentifier', $distribution['tipIdentifier'])->count())
            {
                $sameTipNotPublished++;
                continue;
            }

            // remove id and set distributionId
            $distribution['distributionId'] = $distribution['id'];
            unset($distribution['id']);

            if (! \App\ArchivePublishStatus::where('siteId' , $distribution['siteId'])->where('type' , 'archiveBig')->count())
                \App\ArchivePublishStatus::create([
                    'siteId' => $distribution['siteId'],
                    'type'   => 'archiveBig'
                ]);

            if (! \App\ArchivePublishStatus::where('siteId' , $distribution['siteId'])->where('type' , 'archiveHome')->count())
                \App\ArchivePublishStatus::create([
                    'siteId' => $distribution['siteId'],
                    'type'   => 'archiveHome'
                ]);

			// get the aliases - added by GDM
			$homeTeamAlias = \App\Models\Team\Alias::where('teamId', $distribution['homeTeamId'] )->first();
			if( $homeTeamAlias && $homeTeamAlias->alias && $homeTeamAlias->alias != '' ) {
				$distribution['homeTeam'] = $homeTeamAlias->alias;
			}		
			$awayTeamAlias = \App\Models\Team\Alias::where('teamId', $distribution['awayTeamId'] )->first();
			if( $awayTeamAlias && $awayTeamAlias->alias && $awayTeamAlias->alias != '' ) {
				$distribution['awayTeam'] = $awayTeamAlias->alias;
			}		
			$leagueAlias = \App\Models\League\Alias::where('leagueId', $distribution['leagueId'] )->first();
			if( $leagueAlias && $leagueAlias->alias && $leagueAlias->alias != '' ) {
				$distribution['league'] = $leagueAlias->alias;
			}
			
			$countryAlias = \App\Models\Country\Alias::where('countryCode', $distribution['countryCode'] )->first();
			if( $countryAlias && $countryAlias->alias && $countryAlias->alias != '' ) {
				$distribution['country'] = $countryAlias->alias;
			}
			
			
            // ---- Insert event in archive big
            \App\ArchiveBig::create($distribution);

            // ---- Insert event in archive_home
            $archiveHome = new \App\Http\Controllers\Admin\ArchiveHome();

            // increment order
            $archiveHome->incrementOrder($distribution['siteId'], $distribution['tableIdentifier']);

            // set isVisible for archive home
            $distribution['isVisible'] = 1;
            $distribution['publishDate'] = gmdate('Y-m-d H:i:s');

            if ($distribution['publishTime'])
                $distribution['publishDate'] = gmdate('Y-m-d H:i:s', $distribution['publishTime']);
            
            if ($distribution['isVip'] == 1) {
                // get the order of the last inserted VIP event
                // and link it to the current VIP 
                $previousArchive = ArchiveHome::whereRaw("DATE_FORMAT(eventDate, '%Y-%m-%d') = '" . gmdate('Y-m-d', strtotime($distribution["eventDate"])) . "'")
                    ->where('siteId', $distribution['siteId'])
                    ->where('tableIdentifier', $distribution['tableIdentifier'])
                    ->where("isVip", "=", 1)
                    ->orderBy("order", "ASC")
                    ->first();
                    
                // if no VIP event is found
                // get the last order of the normal events
                if ($previousArchive == NULL) {
                    $previousArchive = ArchiveHome::whereRaw("DATE_FORMAT(eventDate, '%Y-%m-%d') = '" . gmdate('Y-m-d', strtotime($distribution["eventDate"])) . "'")
                        ->where('siteId', $distribution['siteId'])
                        ->where('tableIdentifier', $distribution['tableIdentifier'])
                        ->where("isVip", "=", 0)
                        ->orderBy("order", "DESC")
                        ->first();

                    if ($previousArchive != NULL) {
                        // shift the current list order by 1 index
                        // to insert the new VIP event in the correct position
                        ArchiveHome::where('order', ">", $previousArchive->order + 1)
                            ->where('siteId', $distribution['siteId'])
                            ->where('tableIdentifier', $distribution['tableIdentifier'])
                            ->update(['order' => DB::raw("`order` + 1")]);
                        
                        $distribution["order"] = $previousArchive->order + 1;
                    } else {
                        $distribution["order"] = 0;
                    }
                } else {
                    // shift the current list order by 1 index
                    // to insert the new VIP event in the correct position
                    ArchiveHome::where('order', ">=", $previousArchive->order)
                        ->where('siteId', $distribution['siteId'])
                        ->where('tableIdentifier', $distribution['tableIdentifier'])
                        ->update(['order' => DB::raw("`order` + 1")]);
                        
                    $distribution["order"] = $previousArchive->order;
                }
            }

            // insert event in archive home
            \App\ArchiveHome::create($distribution);

            // delete in adition evetns
            $archiveHome->deleteInAdditionEvents($distribution['siteId'], $distribution['tableIdentifier']);

            $inserted++;
        }

        $message = '';
        if ($alreadyPublish)
            $message .= "$alreadyPublish events already published to archive\r\n";
        if ($inserted)
            $message .= "$inserted events was published to archive\r\n";
        if ($notHaveResultOrStatus)
            $message .= "$notHaveResultOrStatus was NOT published becouse they not have result or status\r\n";
        if ($sameTipNotPublished)
            $message .= "$sameTipNotPublished was NOT inserted in archive becouse they are already published in other packages with same tip\r\n";

        return [
            "type" => "success",
            "message" => $message
        ];
    }

    public function update() {}

    public function destroy() {}

}
