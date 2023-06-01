<?php

namespace App\Console\Commands;

use App\TokenStore\TokenCache;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;

class RetrieveAvailability extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'retrieve:availability';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retrieve alle the conference rooms and there availability from Office 365.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Get the access token from the cache
        $tokenCache = new TokenCache();
        $accessToken = $tokenCache->getAccessToken();

        // Create a Graph client
        $graph = new Graph();
        $graph->setAccessToken($accessToken);

        // Set the current time
        $now = Carbon::now();

        // queryParams to optimize reponse
        $queryParams = [
            'startDateTime' => $now->toIso8601String(),
            'endDateTime' => $now->endOfDay()->toIso8601String(),
            // Only request the properties used
            '$select' => 'subject,organizer,start,end',
            // Sort them by start time
            '$orderby' => 'start/dateTime',
            // Limit results to 1
            '$top' => 1
        ];

        // Retrieve the rooms
        $getRoomsUrl = '/places/microsoft.graph.room';
        $rooms = $graph->createRequest('GET', $getRoomsUrl)
            // Add the user's timezone to the Prefer header
            ->addHeaders(array(
                'Prefer' => 'outlook.timezone="' . Cache::get('userTimeZone') . '"'
            ))
            ->setReturnType(Model\Room::class)
            ->execute();

        foreach ($rooms as $room) {
            $this->info($room->getDisplayName());

            // Retrieve the calendar of the room
            $getEventsUrl = '/users/' . $room->getEmailAddress() . '/calendarView?' . http_build_query($queryParams);
            $events = $graph->createRequest('GET', $getEventsUrl)
                ->addHeaders(array(
                    'Prefer' => 'outlook.timezone="' . Cache::get('userTimeZone') . '"'
                ))
                ->setReturnType(Model\Event::class)
                ->execute();

            if (count($events) == 1) {
                foreach ($events as $event) {
                    $this->line($event->getSubject());
                    $this->line(Carbon::parse($event->getStart()->getDateTime())->toDateTimeString());
                    $this->line(Carbon::parse($event->getEnd()->getDateTime())->toDateTimeString());

                    //We build a hash of the values to compare it with our cache. To prevent the storage of the same image.
                    $hash = sha1(http_build_query([$room->getDisplayName(), $event->getSubject(), $event->getStart(), $event->getEnd()]));

                    if (Storage::exists('public/' . $room->getDisplayName() . '.jpg') && Cache::has($room->getDisplayName())) {
                        if (Cache::get($room->getDisplayName()) == $hash) {
                            //The hash is the same. We can stop here.
                            continue;
                        }
                    } else {
                        Cache::put($room->getDisplayName(), $hash);
                    }

                    $image = new \App\Services\ImageCreator();
                    $image->addText($room->getDisplayName(), (296 / 2), 0)
                        ->addMultiLineText($event->getSubject(), 0, 35, 25, '#000', 'left')
                        ->addLine(10, 25, (296 - 10), 25)
                        ->addLine(10, 26, (296 - 10), 26) //Intervention Image only support line thickness with Imagick.
                        ->addLine(10, 27, (296 - 10), 27) //Intervention Image only support line thickness with Imagick.
                        ->addText(Carbon::parse($event->getStart()->getDateTime())->format('H:i'), 296, 35, 40, '#000', 'right')
                        ->addLine((296 - 90), 70, (296 - 10), 70)
                        ->addLine((296 - 90), 71, (296 - 10), 71) //Intervention Image only support line thickness with Imagick.
                        ->addLine((296 - 90), 72, (296 - 10), 72) //Intervention Image only support line thickness with Imagick.
                        ->addText(Carbon::parse($event->getEnd()->getDateTime())->format('H:i'), 296, 80, 40, '#000', 'right')
                        //->addText(Carbon::now()->toDateTimeString(), 0, 116, 12, '#f00', 'left') //Debug
                        ->save(storage_path('app/public/' . $room->getDisplayName() . '.jpg'));
                }
            } else {
                //We build a hash of the values to compare it with our cache. To prevent the storage of the same image.
                $hash = sha1(http_build_query([$room->getDisplayName()]));

                if (Storage::exists('public/' . $room->getDisplayName() . '.jpg') && Cache::has($room->getDisplayName())) {
                    if (Cache::get($room->getDisplayName()) == $hash) {
                        //The hash is the same. We can stop here.
                        continue;
                    }
                } else {
                    Cache::put($room->getDisplayName(), $hash);
                }

                $image = new \App\Services\ImageCreator();
                $image->addText($room->getDisplayName(), (296 / 2), 0)
                    ->addLine(10, 25, (296 - 10), 25)
                    ->addLine(10, 26, (296 - 10), 26) //Intervention Image only support line thickness with Imagick.
                    ->addLine(10, 27, (296 - 10), 27) //Intervention Image only support line thickness with Imagick.
                    ->addText('Beschikbaar', (296 / 2), 50, 50)
                    //->addText(Carbon::now()->toDateTimeString(), 0, 116, 12, '#f00', 'left') //Debug
                    ->save(storage_path('app/public/' . $room->getDisplayName() . '.jpg'));
            }
        }

        // Retrieve all the files
        $files = Storage::allFiles();
        foreach ($files as $file) {
            $filename = pathinfo($file, PATHINFO_FILENAME);

            //Check if there is a mac-address available
            if (array_key_exists($filename, config('openepaperlink.tags'))) {
                $mac = config('openepaperlink.tags.' . $filename);
                $time = Carbon::createFromTimestamp(Storage::lastModified($file));

                //Only update the image if it's created in the past minute
                if ($time->gt(Carbon::now()->subMinute())) {
                    
                    try {
                        $response = Http::attach(
                            'file',
                            Storage::get($file),
                            $file
                        )->post(config('openepaperlink.server'), [
                            'dither' => 0,
                            'mac' => $mac,
                        ]);

                        if ($response->successful()) {
                            $this->info($mac);
                        } else {
                            $this->error($mac);
                        }
                    } catch (Exception $e) {
                        $this->error($e->getMessage());
                    }
                }
            }
        }

        return Command::SUCCESS;
    }
}
