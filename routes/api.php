<?php

use App\Http\Controllers\Api\GitHubController;
use App\Http\Controllers\Api\PlayerController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\UserController;
use App\Http\Responses\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/v1/ip-address', function (Request $request) {
    JsonResponse::sendSuccess($request, ['ip_address' => gethostbyname(gethostname())]);
})->name('ipAddress-get');

Route::post('/v1/users', [UserController::class, 'createUser'])->name('user-createUser');





/*
|-------------------------------------------------------------------------------------------------------
| Endpointy podlegające procesowi autoryzacji
|-------------------------------------------------------------------------------------------------------
*/

Route::patch('/v1/users/me', [UserController::class, 'updateUser'])->name('user-updateUser');

Route::post('/v1/rooms', [RoomController::class, 'createRoom'])->name('room-createRoom')->middleware('throttle:creatingRoomLimit');
Route::patch('/v1/rooms/my/last', [RoomController::class, 'updateRoom'])->name('room-updateRoom');

Route::post('/v1/players', [PlayerController::class, 'createPlayer'])->name('player-createPlayer');
Route::patch('/v1/players/my/last', [PlayerController::class, 'updatePlayer'])->name('player-updatePlayer');
Route::put('/v1/players/{player}/status', [PlayerController::class, 'setStatus'])->name('player-setStatus');
Route::put('/v1/players/{player}/role', [PlayerController::class, 'setRole'])->name('player-setRole');





/*
|---------------------------------------------------------------------------------------------------------------
| Endpointy do odbierania informacji z serwisu GitHub
|---------------------------------------------------------------------------------------------------------------
*/

Route::middleware('throttle:githubLimit')->group(function () {
    Route::post('/v1/github/pull', [GitHubController::class, 'pull'])->name('github-pull');
});
