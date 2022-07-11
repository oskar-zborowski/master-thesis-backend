<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\ErrorCodes\DefaultErrorCode;
use App\Http\Libraries\Encrypter;
use App\Http\Libraries\Validation;
use App\Http\Requests\CreatePlayerRequest;
use App\Http\Requests\SetRoleRequest;
use App\Http\Requests\SetStatusRequest;
use App\Http\Requests\UpdatePlayerRequest;
use App\Http\Responses\JsonResponse;
use App\Models\Player;
use App\Models\Room;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PlayerController extends Controller
{
    /**
     * #### `POST` `/api/v1/players`
     * Stworzenie nowego gracza (dołączenie do pokoju)
     */
    public function createPlayer(CreatePlayerRequest $request) {

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->name === null) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.you-must-set-player-name'),
                __FUNCTION__
            );
        }

        $encryptedCode = Encrypter::encrypt($request->code, 6, false);
        $aesDecrypt = Encrypter::prepareAesDecrypt('code', $encryptedCode);

        /** @var Room $room */
        $room = Room::whereRaw($aesDecrypt)->orderBy('id', 'desc')->first();

        /** @var Player $player */
        $player = $user->players()->where('status', 'CONNECTED')->orderBy('id', 'desc')->first();

        if ($player) {

            /** @var Room $lastRoom */
            $lastRoom = $player->room()->first();

            if ($lastRoom->status != 'GAME_OVER' && (!$room || $room->id != $lastRoom->id)) {
                throw new ApiException(
                    DefaultErrorCode::PERMISSION_DENIED(),
                    __('validation.custom.you-are-already-in-another-room'),
                    __FUNCTION__
                );
            }
        }

        if (!$room) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.incorrect-code'),
                __FUNCTION__
            );
        }

        /** @var Player $player */
        $player = $room->players()->where('user_id', $user->id)->first();

        if ($player) {

            if ($player->status == 'BANNED') {

                throw new ApiException(
                    DefaultErrorCode::PERMISSION_DENIED(),
                    __('validation.custom.you-have-been-banned'),
                    __FUNCTION__
                );

            } else if (in_array($player->status, ['LEFT', 'DISCONNECTED'])) {

                if ($room->voting_type == 'START' || $room->status != 'WAITING_IN_ROOM') {
                    throw new ApiException(
                        DefaultErrorCode::PERMISSION_DENIED(),
                        __('validation.custom.game-already-started'),
                        __FUNCTION__
                    );
                }

                $this->checkRoomLimit($room);
                $this->checkAvatarExistence($player, $room);

            }

            if ($room->status == 'GAME_OVER') {
                throw new ApiException(
                    DefaultErrorCode::PERMISSION_DENIED(),
                    __('validation.custom.game-is-over'),
                    __FUNCTION__
                );
            }

        } else if ($room->voting_type == 'START' || $room->status != 'WAITING_IN_ROOM') {

            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(),
                __('validation.custom.game-already-started'),
                __FUNCTION__
            );

        } else {

            $this->checkRoomLimit($room);

            $player = new Player;
            $player->room_id = $room->id;
            $player->user_id = $user->id;

            $avatar = $user->default_avatar;

            /** @var Player $isAvatarExists */
            $isAvatarExists = $room->players()->where([
                'avatar' => $avatar,
                'status' => 'CONNECTED',
            ])->first();

            if ($isAvatarExists) {
                $avatar = $this->findAvailableAvatar($room);
            }

            $player->avatar = $avatar;
            $player->expected_time_at = date('Y-m-d H:i:s', strtotime('+' . env('ROOM_REFRESH') . ' seconds', strtotime(now())));
            $player->save();

            $user->default_avatar = $avatar;
            $user->save();
        }

        $room = $room->fresh();

        JsonResponse::sendSuccess($request, $room->getData(), null, 201);
    }

    /**
     * #### `PATCH` `/api/v1/players/my/last`
     * Edycja danych gracza (zmiana parametrów podczas oczekiwania w pokoju i w trakcie gry)
     */
    public function updatePlayer(UpdatePlayerRequest $request) {

        $reloadRoom = false;

        /** @var \App\Models\User $user */
        $user = Auth::user();

        /** @var Player $player */
        $player = $user->players()->whereIn('status', ['CONNECTED', 'DISCONNECTED', 'BANNED'])->orderBy('id', 'desc')->first();

        if (!$player) {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(),
                __('validation.custom.no-permission'),
                __FUNCTION__
            );
        }

        if ($player->status == 'BANNED') {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(),
                __('validation.custom.you-have-been-banned'),
                __FUNCTION__
            );
        }

        /** @var Room $room */
        $room = $player->room()->first();

        if ($player->status == 'DISCONNECTED') {

            if ($room->voting_type == 'START') {

                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.game-already-started'),
                    __FUNCTION__
                );

            } else if ($room->status == 'WAITING_IN_ROOM') {
                $this->checkRoomLimit($room);
                $this->checkAvatarExistence($player, $room);
                $reloadRoom = true;
            }
        }

        if ($request->avatar !== null) {

            if ($room->voting_type == 'START' || $room->status != 'WAITING_IN_ROOM') {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.game-being-prepared'),
                    __FUNCTION__
                );
            }

            /** @var Player $avatarExists */
            $avatarExists = $room->players()->where([
                'avatar' => $request->avatar,
                'status' => 'CONNECTED',
            ])->first();

            if ($avatarExists) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.avatar-busy'),
                    __FUNCTION__
                );
            }

            $player->avatar = $request->avatar;
            $player->save();

            $user->default_avatar = $request->avatar;
            $user->save();

            $reloadRoom = true;
        }

        if ($room->status == 'GAME_IN_PROGRESS') {

            Validation::checkGpsLocation($request->gps_location);

            if (!in_array($player->role, ['THIEF', 'AGENT'])) {
                $player->global_position = DB::raw("ST_GeomFromText('POINT($request->gps_location)')");
            }

            $player->hidden_position = DB::raw("ST_GeomFromText('POINT($request->gps_location)')");
            $player->save();

            $reloadRoom = true;
        }

        if ($request->status !== null) {

            $player->status = $request->status;
            $player->save();

            if ($player->user_id == $room->host_id) {

                /** @var Player[] $newHosts */
                $newHosts = $room->players()->where('status', 'CONNECTED')->get();

                if (empty($newHosts)) {
                    /** @var Player[] $newHosts */
                    $newHosts = $room->players()->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->get();
                }

                if (!empty($newHosts)) {

                    $newHostUserId = null;
                    $newHostsNumber = count($newHosts);
                    $randNewHost = rand(1, $newHostsNumber);

                    foreach ($newHosts as $newHost) {

                        $randNewHost--;

                        if ($randNewHost == 0) {
                            $newHostUserId = $newHost->user_id;
                            break;
                        }
                    }

                    $room->host_id = $newHostUserId;
                    $room->save();
                }
            }

            $reloadRoom = true;
        }

        if ($request->voting_type !== null) {

            $this->startVoting($room, $player, $request->voting_type, $request->is_replenishment_with_bots);

            $room->reporting_user_id = $user->id;
            $room->voting_type = $request->voting_type;
            $room->voting_ended_at = date('Y-m-d H:i:s', strtotime('+' . env('VOTING_DURATION') . ' seconds', strtotime(now())));
            $room->save();

            if (!in_array($request->voting_type, ['START', 'RESUME'])) {
                $player->next_voting_starts_at = date('Y-m-d H:i:s', strtotime('+' . env('BLOCKING_TIME_VOTING_START') . ' seconds', strtotime(now())));
            }

            $player->voting_answer = true;
            $player->save();

            $reloadRoom = true;

            shell_exec("php {$_SERVER['DOCUMENT_ROOT']}/../artisan voting:check $room->id $user->id >/dev/null 2>/dev/null &");
        }

        if ($request->voting_answer !== null) {

            if ($room->voting_type === null) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.no-permission'),
                    __FUNCTION__
                );
            }

            if ($player->voting_answer !== null) {
                throw new ApiException(
                    DefaultErrorCode::PERMISSION_DENIED(true),
                    __('validation.custom.no-permission'),
                    __FUNCTION__,
                    false
                );
            }

            $player->voting_answer = $request->voting_answer;
            $player->save();

            $reloadRoom = true;
        }

        if ($request->use_white_ticket) {

            if ($room->status != 'GAME_IN_PROGRESS' || $player->role != 'PEGASUS') {
                throw new ApiException(
                    DefaultErrorCode::PERMISSION_DENIED(),
                    __('validation.custom.no-permission'),
                    __FUNCTION__
                );
            }

            if ($player->config['white_ticket']['number'] - $player->config['white_ticket']['used_number'] <= 0) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.no-white-ticket-available'),
                    __FUNCTION__
                );
            }

            /** @var Player[] $thieves */
            $thieves = $room->players()->where('role', 'THIEF')->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->get();

            foreach ($thieves as $thief) {

                if ($thief->black_ticket_finished_at === null || now() > $thief->black_ticket_finished_at) {

                    if ($thief->fake_position_finished_at && now() <= $thief->fake_position_finished_at) {

                        if ($room->config['actor']['policeman']['visibility_radius'] != -1) {

                            $disclosureThief = DB::select(DB::raw("SELECT id FROM players WHERE room_id == $room->id AND status == 'CONNECTED' AND role <> 'THIEF' AND ST_Distance_Sphere($thief->fake_position, hidden_position) <= {$room->config['actor']['policeman']['visibility_radius']}"));

                            if (!empty($disclosureThief)) {
                                $thief->global_position = $thief->fake_position;
                            }

                        } else {
                            $thief->global_position = $thief->fake_position;
                        }

                    } else {
                        $thief->global_position = $thief->hidden_position;
                    }

                    $thief->save();
                }
            }

            $player->config['white_ticket']['used_number'] = $player->config['white_ticket']['used_number'] + 1;
            $player->save();

            $reloadRoom = true;
        }

        if ($request->use_black_ticket) {

            if ($room->status != 'GAME_IN_PROGRESS' || $player->role != 'THIEF') {
                throw new ApiException(
                    DefaultErrorCode::PERMISSION_DENIED(),
                    __('validation.custom.no-permission'),
                    __FUNCTION__
                );
            }

            if ($player->black_ticket_finished_at) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.black-ticket-active'),
                    __FUNCTION__
                );
            }

            if ($player->config['black_ticket']['number'] - $player->config['black_ticket']['used_number'] <= 0) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.no-black-ticket-available'),
                    __FUNCTION__
                );
            }

            $player->config['black_ticket']['used_number'] = $player->config['black_ticket']['used_number'] + 1;
            $player->black_ticket_finished_at = date('Y-m-d H:i:s', strtotime('+' . $room->config['actor']['thief']['black_ticket']['duration'] . ' seconds', strtotime(now())));
            $player->save();

            $reloadRoom = true;
        }

        if ($request->use_fake_position !== null) {

            if ($room->status != 'GAME_IN_PROGRESS' || $player->role != 'THIEF') {
                throw new ApiException(
                    DefaultErrorCode::PERMISSION_DENIED(),
                    __('validation.custom.no-permission'),
                    __FUNCTION__
                );
            }

            if ($player->fake_position_finished_at) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.fake-position-active'),
                    __FUNCTION__
                );
            }

            if ($player->config['fake_position']['number'] - $player->config['fake_position']['used_number'] <= 0) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.no-fake-position-available'),
                    __FUNCTION__
                );
            }

            Validation::checkGpsLocation($request->use_fake_position);

            $isTouches = DB::select(DB::raw("SELECT ST_TOUCHES($room->boundary_polygon, ST_GeomFromText('POINT($request->use_fake_position)')) AS isTouches"));
            $isContains = DB::select(DB::raw("SELECT ST_CONTAINS($room->boundary_polygon, ST_GeomFromText('POINT($request->use_fake_position)')) AS isContains"));

            if (!$isTouches[0]->isTouches && !$isContains[0]->isContains) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.location-beyond-boundary'),
                    __FUNCTION__
                );
            }

            $player->config['fake_position']['used_number'] = $player->config['fake_position']['used_number'] + 1;
            $player->fake_position = DB::raw("ST_GeomFromText('POINT($request->use_fake_position)')");
            $player->fake_position_finished_at = date('Y-m-d H:i:s', strtotime('+' . $room->config['actor']['thief']['fake_position']['duration'] . ' seconds', strtotime(now())));
            $player->save();

            $reloadRoom = true;
        }

        if ($reloadRoom) {
            $room = $room->fresh();
        }

        $this->checkGameCourse($player);

        JsonResponse::sendSuccess($request, $room->getData());
    }

    /**
     * #### `PUT` `/v1/players/{player}/status`
     * Ustawienie statusu gracza (endpoint tylko dla hosta, host nie może zmieniać swojego statusu - musi skorzystać w tym celu z updatePlayer)
     */
    public function setStatus(Player $player, SetStatusRequest $request) {

        /** @var \App\Models\User $user */
        $user = Auth::user();

        /** @var Room $room */
        $room = $player->room()->first();

        if ($user->id != $room->host_id || $user->id == $player->user_id ||
            in_array($room->voting_type, ['START', 'RESUME']) ||
            !in_array($room->status, ['WAITING_IN_ROOM', 'GAME_PAUSED']) ||
            in_array($player->status, ['CONNECTED', 'DISCONNECTED']) && $request->status == 'LEFT')
        {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(true),
                __('validation.custom.no-permission'),
                __FUNCTION__,
                false
            );
        }

        if ($room->status == 'WAITING_IN_ROOM') {
            $player->role = null;
        }

        $player->status = $request->status;
        $player->save();

        $room = $room->fresh();

        JsonResponse::sendSuccess($request, $room->getData());
    }

    /**
     * #### `PUT` `/v1/players/{player}/role`
     * Ustawienie roli gracza (endpoint tylko dla hosta)
     */
    public function setRole(Player $player, SetRoleRequest $request) {

        /** @var \App\Models\User $user */
        $user = Auth::user();

        /** @var Room $room */
        $room = $player->room()->first();

        if ($user->id != $room->host_id || $room->voting_type == 'START' || $room->status != 'WAITING_IN_ROOM') {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(true),
                __('validation.custom.no-permission'),
                __FUNCTION__,
                false
            );
        }

        if ($player->status != 'CONNECTED') {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.user-is-not-in-room'),
                __FUNCTION__
            );
        }

        /** @var Player[] $players */
        $players = $room->players()->where([
            'role' => $request->role,
            'status' => 'CONNECTED',
        ])->get();

        $playersNumber = count($players);

        if ($request->role !== null && $request->role != $player->role &&
            $playersNumber >= $room->config['actor'][strtolower($request->role)]['number'])
        {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.max-player-number-reached'),
                __FUNCTION__
            );
        }

        $player->role = $request->role;
        $player->save();

        $room = $room->fresh();

        JsonResponse::sendSuccess($request, $room->getData());
    }

    private function findAvailableAvatar(Room $room) {

        $i = 0;
        $avatars = Validation::getAvatars();
        shuffle($avatars);

        do {

            $avatar = $avatars[$i++];

            /** @var Player $isAvatarExists */
            $isAvatarExists = $room->players()->where([
                'avatar' => $avatar,
                'status' => 'CONNECTED',
            ])->first();

        } while ($isAvatarExists);

        return $avatar;
    }

    private function startVoting(Room $room, Player $player, string $votingType, ?bool $isReplenishmentWithBots) {

        if ($room->voting_type) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.voting-already-started'),
                __FUNCTION__
            );
        }

        if (!in_array($votingType, ['START', 'RESUME'])) {

            $now = now();

            if ($player->next_voting_starts_at && $now < $player->next_voting_starts_at) {

                $timeDifference = strtotime($player->next_voting_starts_at) - strtotime($now);

                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.voting-limit', ['seconds' => $timeDifference]),
                    __FUNCTION__
                );
            }
        }

        if ($votingType == 'START') {

            /** @var Player[] $allPlayers */
            $allPlayers = $room->players()->where('status', 'CONNECTED')->get();
            $allPlayersNumber = count($allPlayers);

            if ($player->user_id != $room->host_id || $room->status != 'WAITING_IN_ROOM') {

                throw new ApiException(
                    DefaultErrorCode::PERMISSION_DENIED(true),
                    __('validation.custom.no-permission'),
                    __FUNCTION__,
                    false
                );

            } else if ($room->boundary_points === null) {

                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.complete-boundary'),
                    __FUNCTION__
                );

            } else if ($room->config['actor']['policeman']['number'] + $room->config['actor']['thief']['number'] < $allPlayersNumber) {

                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.players-number-exceeded'),
                    __FUNCTION__
                );

            } else if (!$isReplenishmentWithBots && $room->config['actor']['policeman']['number'] + $room->config['actor']['thief']['number'] > $allPlayersNumber) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.not-enough-players'),
                    __FUNCTION__
                );
            }
        }

        if ($votingType == 'ENDING_COUNTDOWN' && ($room->status != 'GAME_IN_PROGRESS' || now() >= $room->game_started_at)) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.game-already-started'),
                __FUNCTION__
            );
        }

        if ($votingType == 'PAUSE' && $room->status != 'GAME_IN_PROGRESS') {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.no-permission'),
                __FUNCTION__
            );
        }

        if ($votingType == 'RESUME' && ($player->user_id != $room->host_id || $room->status != 'GAME_PAUSED')) {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(true),
                __('validation.custom.no-permission'),
                __FUNCTION__,
                false
            );
        }

        if (in_array($votingType, ['END_GAME', 'GIVE_UP']) && $room->status == 'GAME_OVER') {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.no-permission'),
                __FUNCTION__
            );
        }
    }

    private function checkRoomLimit(Room $room) {

        /** @var Player[] $allPlayers */
        $allPlayers = $room->players()->where('status', 'CONNECTED')->get();
        $allPlayersNumber = count($allPlayers);

        if ($allPlayersNumber >= $room->config['actor']['policeman']['number'] + $room->config['actor']['thief']['number']) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.max-player-number-reached'),
                __FUNCTION__
            );
        }
    }

    private function checkAvatarExistence(Player $player, Room $room) {

        $avatar = $player->avatar;

        /** @var Player $isAvatarExists */
        $isAvatarExists = $room->players()->where([
            'avatar' => $avatar,
            'status' => 'CONNECTED',
        ])->first();

        if ($isAvatarExists) {
            $avatar = $this->findAvailableAvatar($room);
        }

        $player->avatar = $avatar;
        $player->role = null;
        $player->status = 'CONNECTED';
        $player->save();

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $user->default_avatar = $avatar;
        $user->save();
    }

    private function checkGameCourse(Player $player) {
    }
}
