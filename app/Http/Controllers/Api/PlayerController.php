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
        $player = $user->players()->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->orderBy('id', 'desc')->first();

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

            } else if ($player->status == 'LEFT') {

                if ($room->voting_type == 'START' || $room->status != 'WAITING_IN_ROOM') {
                    throw new ApiException(
                        DefaultErrorCode::PERMISSION_DENIED(),
                        __('validation.custom.game-already-started'),
                        __FUNCTION__
                    );
                }

                $avatar = $player->avatar;

                /** @var Player $isAvatarExists */
                $isAvatarExists = $room->players()->where('avatar', $avatar)->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->first();

                if ($isAvatarExists) {
                    $avatar = $this->findAvailableAvatar($room);
                }

                $player->avatar = $avatar;
                $player->role = null;
                $player->status = 'CONNECTED';
                $player->save();

                $user->default_avatar = $avatar;
                $user->save();

            } else if ($player->status == 'DISCONNECTED') {
                $player->status = 'CONNECTED';
                $player->save();
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

            $player = new Player;
            $player->room_id = $room->id;
            $player->user_id = $user->id;

            $avatar = $user->default_avatar;

            /** @var Player $isAvatarExists */
            $isAvatarExists = $room->players()->where('avatar', $avatar)->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->first();

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

        if ($request->avatar !== null) {

            if ($room->voting_type == 'START' || $room->status != 'WAITING_IN_ROOM') {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.game-being-prepared'),
                    __FUNCTION__
                );
            }

            /** @var Player $avatarExists */
            $avatarExists = $room->players()->where('avatar', $request->avatar)->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->first();

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

            $reloadRoom = true;
        }

        if ($request->voting_type !== null) {

            $this->startVoting($room, $player, $request->voting_type);

            $room->reporting_user_id = $user->id;
            $room->voting_type = $request->voting_type;
            $room->voting_ended_at = date('Y-m-d H:i:s', strtotime('+' . env('VOTING_DURATION') . ' seconds', strtotime(now())));
            $room->save();

            if (!in_array($request->voting_type, ['START', 'RESUME'])) {

                $player->next_voting_starts_at = date('Y-m-d H:i:s', strtotime('+' . env('BLOCKING_TIME_VOTING_START') . ' seconds', strtotime(now())));
                $player->save();

                $reloadRoom = true;
            }
        }

        if ($request->voting_answer !== null) {

            if (!$room->voting_type) {
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

                if ($thief->fake_position) {
                    $thief->global_position = $thief->fake_position;
                } else {
                    $thief->global_position = $thief->hidden_position;
                }

                $thief->save();
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

            $player->config['fake_position']['used_number'] = $player->config['fake_position']['used_number'] + 1;
            $player->fake_position = DB::raw("ST_GeomFromText('POINT($request->use_fake_position)')");
            $player->fake_position_finished_at = date('Y-m-d H:i:s', strtotime('+' . $room->config['actor']['thief']['fake_position']['duration'] . ' seconds', strtotime(now())));
            $player->save();

            $reloadRoom = true;
        }

        if ($reloadRoom) {
            $room = $room->fresh();
        }

        $this->checkGameCourse($room);

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

        if ($user->id != $room->host_id || $room->voting_type == 'START' ||
            $room->status != 'WAITING_IN_ROOM' || $room->config['other']['is_role_random'])
        {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(true),
                __('validation.custom.no-permission'),
                __FUNCTION__,
                false
            );
        }

        if (!in_array($player->status, ['CONNECTED', 'DISCONNECTED'])) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.user-is-not-in-room'),
                __FUNCTION__
            );
        }

        /** @var Player[] $players */
        $players = $room->players()->where('role', $request->role)->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->get();
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
            $isAvatarExists = $room->players()->where('avatar', $avatar)->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->first();

        } while ($isAvatarExists);

        return $avatar;
    }

    private function startVoting(Room $room, Player $player, string $votingType) {

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

        if ($votingType == 'START' && ($player->user_id != $room->host_id || $room->status != 'WAITING_IN_ROOM')) {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(true),
                __('validation.custom.no-permission'),
                __FUNCTION__,
                false
            );
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

    private function checkGameCourse(Room $room) {

        if ($room->voting_type) {

            $votingEnd = false;
            $timeIsUp = false;

            if (now() < $room->voting_ended_at) {

                $votersNumber = 0;

                /** @var Player[] $players */
                $players = $room->players()->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->get();

                foreach ($players as $player) {
                    if ($player->voting_answer !== null) {
                        $votersNumber++;
                    } else {
                        break;
                    }
                }

                if ($votersNumber == count($players)) {
                    $votingEnd = true;
                }

            } else {
                $votingEnd = true;
                $timeIsUp = true;
            }

            if ($votingEnd) {

                $successfulVote = false;
                $playersNumberFromCatchingFaction = 0;
                $playersNumberFromThievesFaction = 0;
                $confirmationsNumberFromCatchingFaction = 0;
                $confirmationsNumberFromThievesFaction = 0;

                if ($timeIsUp) {
                    /** @var Player[] $players */
                    $players = $room->players()->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->get();
                }

                foreach ($players as $player) {

                    if ($player->role == 'THIEF') {

                        if ($player->voting_answer) {
                            $confirmationsNumberFromThievesFaction++;
                        }

                        $playersNumberFromThievesFaction++;

                    } else {

                        if ($player->voting_answer) {
                            $confirmationsNumberFromCatchingFaction++;
                        }

                        $playersNumberFromCatchingFaction++;
                    }
                }

                if (in_array($room->voting_type, ['START', 'ENDING_COUNTDOWN', 'RESUME']) &&
                    $confirmationsNumberFromCatchingFaction == $playersNumberFromCatchingFaction && $confirmationsNumberFromThievesFaction == $playersNumberFromThievesFaction)
                {
                    /** @var Player $reportingUser */
                    $reportingUser = $room->players()->where('user_id', $room->reporting_user_id)->first();
                    $reportingUser->next_voting_starts_at = null;
                    $reportingUser->save();

                    $successfulVote = true;

                } else if (in_array($room->voting_type, ['PAUSE', 'END_GAME']) &&
                    $confirmationsNumberFromCatchingFaction / $playersNumberFromCatchingFaction > 0.5 && $confirmationsNumberFromThievesFaction / $playersNumberFromThievesFaction > 0.5)
                {
                    /** @var Player $reportingUser */
                    $reportingUser = $room->players()->where('user_id', $room->reporting_user_id)->first();
                    $reportingUser->next_voting_starts_at = null;
                    $reportingUser->save();

                    $successfulVote = true;

                } else if ($room->voting_type == 'GIVE_UP') {

                    /** @var Player $reportingUser */
                    $reportingUser = $room->players()->where('user_id', $room->reporting_user_id)->first();

                    if ($reportingUser->role != 'THIEF' &&
                        $confirmationsNumberFromCatchingFaction / $playersNumberFromCatchingFaction > 0.5)
                    {
                        $reportingUser->next_voting_starts_at = null;
                        $reportingUser->save();

                        $successfulVote = true;

                    } else if ($reportingUser->role == 'THIEF' &&
                        $confirmationsNumberFromThievesFaction / $playersNumberFromThievesFaction > 0.5)
                    {
                        $reportingUser->next_voting_starts_at = null;
                        $reportingUser->save();

                        $successfulVote = true;
                    }
                }

                if ($successfulVote) {

                    foreach ($players as $player) {
                        $player->voting_answer = null;
                        $player->save();
                    }

                } else {

                    foreach ($players as $player) {
                        $player->voting_answer = null;
                        $player->failed_voting_type = $room->voting_type;
                        $player->save();
                    }
                }

                $room->reporting_user_id = null;
                $room->voting_type = null;
                $room->voting_ended_at = null;
                $room->save();
            }
        }
    }
}
