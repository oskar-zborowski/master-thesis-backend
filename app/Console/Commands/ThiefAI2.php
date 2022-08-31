<?php

namespace App\Console\Commands;

use App\Http\Libraries\Geometry;
use App\Models\Room;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use MatanYadaev\EloquentSpatial\Objects\Point;

class ThiefAI2 extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'thief-ai2:start {roomId}';

    /**
     * The console command description.
     */
    protected $description = 'Start the Thief AI';

    /**
     * Execute the console command.
     */
    public function handle() {

        $roomId = $this->argument('roomId');

        /** @var Room $room */
        $room = Room::where('id', $roomId)->first();

        if (!$room || $room->status != 'GAME_IN_PROGRESS') {
            die;
        }

        $boundaryXYString = Geometry::convertGeometryLatLngToXY($room->boundary_points);
        $boundaryCenterXYQuery = DB::select(DB::raw("SELECT ST_AsText(ST_Centroid(ST_GeomFromText('POLYGON(($boundaryXYString))'))) AS boundaryCenter"));
        $boundaryCenterXY = substr($boundaryCenterXYQuery[0]->boundaryCenter, 6, -1);
        $boundaryCenterXYArray = explode(' ', $boundaryCenterXY);

        $boundaryCenterXYPoint['x'] = $boundaryCenterXYArray[0];
        $boundaryCenterXYPoint['y'] = $boundaryCenterXYArray[1];

        $boundaryCenterLatLngPoint = Geometry::convertXYToLatLng($boundaryCenterXYPoint);

        $startLocationLatLngPoint = "{$boundaryCenterLatLngPoint['x']} {$boundaryCenterLatLngPoint['y']}";

        /** @var \App\Models\Player[] $thievesWithoutLocation */
        $thievesWithoutLocation = $room->players()->whereNull('hidden_position')->where([
            'role' => 'THIEF',
            'is_bot' => true,
        ])->get();

        foreach ($thievesWithoutLocation as $tWL) {
            $tWL->hidden_position = DB::raw("ST_GeomFromText('POINT($startLocationLatLngPoint)')");
            $tWL->save();
        }

        $lastSavedTime = microtime(true);
        $lastDestinationLatLng = null;

        $nXY = null;
        $eXY = null;
        $sXY = null;
        $wXY = null;

        $boundaryXYArray = explode(',', $boundaryXYString);

        foreach ($boundaryXYArray as $singleBoundaryPointXYString) {

            $singleBoundaryPointXYArray = explode(' ', $singleBoundaryPointXYString);

            $singleBoundaryPointXY['x'] = $singleBoundaryPointXYArray[0];
            $singleBoundaryPointXY['y'] = $singleBoundaryPointXYArray[1];

            if ($nXY === null || $singleBoundaryPointXY['y'] > $nXY) {
                $nXY = $singleBoundaryPointXY['y'];
            }

            if ($sXY === null || $singleBoundaryPointXY['y'] < $sXY) {
                $sXY = $singleBoundaryPointXY['y'];
            }

            if ($eXY === null || $singleBoundaryPointXY['x'] > $eXY) {
                $eXY = $singleBoundaryPointXY['x'];
            }

            if ($wXY === null || $singleBoundaryPointXY['x'] < $wXY) {
                $wXY = $singleBoundaryPointXY['x'];
            }
        }

        do {

            sleep(env('BOT_REFRESH'));

            /** @var Room $room */
            $room = Room::where('id', $roomId)->first();

            if (!$room || $room->status != 'GAME_IN_PROGRESS') {
                break;
            }

            /** @var \App\Models\Player[] $thieves */
            $thieves = $room->players()->where([
                'role' => 'THIEF',
                'is_bot' => true,
            ])->get();

            /** @var \App\Models\Player[] $policemen */
            $policemen = $room->players()->whereIn('role', ['POLICEMAN', 'PEGASUS', 'EAGLE', 'FATTY_MAN'])->where('is_bot', true)->get();

            foreach ($thieves as $thiefTemp) {

                $thiefTemp->mergeCasts([
                    'hidden_position' => Point::class,
                ]);

                $currentPositionLatLng['x'] = $thiefTemp->hidden_position->longitude;
                $currentPositionLatLng['y'] = $thiefTemp->hidden_position->latitude;
                $currentPositionXY = Geometry::convertLatLngToXY($currentPositionLatLng);

                break;
            }

            $randNewDestination = true;

            if ($lastDestinationLatLng !== null) {

                $randNewDestination = false;

                if ($this->checkEnemiesPosition($policemen, $lastDestinationLatLng, $room, $currentPositionXY)) {
                    $randNewDestination = true;
                }
            }

            if ($randNewDestination) {

                do {

                    $randLatXY = rand($sXY, $nXY);
                    $randLngXY = rand($wXY, $eXY);

                    $newDestinationXY['x'] = $randLngXY;
                    $newDestinationXY['y'] = $randLatXY;

                    $newDestinationLatLng = Geometry::convertXYToLatLng($newDestinationXY);

                    $checkEnemiesPositionStatus = $this->checkEnemiesPosition($policemen, $newDestinationLatLng, $room, $currentPositionXY);

                } while ($checkEnemiesPositionStatus);

                $lastDestinationLatLng = $newDestinationLatLng;
            }

            $lastDestinationXY = Geometry::convertLatLngToXY($lastDestinationLatLng);

            $botShift = $room->config['other']['bot_speed'] * (microtime(true) - $lastSavedTime);
            $finalPositionXY = Geometry::getShiftedPoint($currentPositionXY, $lastDestinationXY, $botShift);
            $finalPositionLatLng = Geometry::convertXYToLatLng($finalPositionXY);
            $finalPositionXYString = "{$finalPositionXY['x']} {$finalPositionXY['y']}";
            $finalPositionLatLngString = "{$finalPositionLatLng['x']} {$finalPositionLatLng['y']}";

            $isIntersects = DB::select(DB::raw("SELECT ST_Intersects(ST_GeomFromText('POLYGON(($boundaryXYString))'), ST_GeomFromText('POINT($finalPositionXYString)')) AS isIntersects"));

            if ($isIntersects[0]->isIntersects) {

                foreach ($thieves as $thief) {
                    $thief->hidden_position = DB::raw("ST_GeomFromText('POINT($finalPositionLatLngString)')");
                    $thief->save();
                }

            } else {
                $lastDestinationLatLng = null;
            }

            $lastSavedTime = microtime(true);

        } while ($room->status == 'GAME_IN_PROGRESS');
    }

    private static function checkEnemiesPosition($policemen, $lastDestinationLatLng, $room, $currentPositionXY) {

        $randNewDestination = false;

        $lastDestinationXY = Geometry::convertLatLngToXY($lastDestinationLatLng);

        foreach ($policemen as $policeman) {

            if ($policeman->role == 'EAGLE') {
                $policemanCatchingRadius = $room->config['actor']['policeman']['catching']['radius'] * 2;
            } else {
                $policemanCatchingRadius = $room->config['actor']['policeman']['catching']['radius'];
            }

            $policeman->mergeCasts([
                'hidden_position' => Point::class,
            ]);

            $policemanPositionLatLng['x'] = $policeman->hidden_position->longitude;
            $policemanPositionLatLng['y'] = $policeman->hidden_position->latitude;
            $policemanPositionXY = Geometry::convertLatLngToXY($policemanPositionLatLng);

            if (Geometry::checkIfPointBelongsToSegment2($policemanPositionXY, $currentPositionXY, $lastDestinationXY)) {

                $intersectionPointAndLineXY = Geometry::findIntersectionPointAndLine($policemanPositionXY, $currentPositionXY, $lastDestinationXY);

                if ($intersectionPointAndLineXY !== false) {

                    $intersectionPointAndLineLatLng = Geometry::convertXYToLatLng($intersectionPointAndLineXY);

                    if (Geometry::getSphericalDistanceBetweenTwoPoints($intersectionPointAndLineLatLng, $policemanPositionLatLng) <= $policemanCatchingRadius) {
                        $randNewDestination = true;
                        break;
                    }
                }
            }
        }

        return $randNewDestination;
    }
}
