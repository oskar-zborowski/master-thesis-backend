<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\JsonResponse;
use Illuminate\Http\Request;

class GitHubController extends Controller
{
    /**
     * #### `POST` `/api/v1/github/pull`
     * Zaciągnięcie nowych zmian ze zdalnego repozytorium
     */
    public function pull(Request $request) {

        $githubAccount = env('GITHUB_ACCOUNT');
        $githubToken = env('GITHUB_TOKEN');
        $githubRepository = env('GITHUB_REPOSITORY');
        $githubBranch = env('GITHUB_BRANCH');

        shell_exec("git pull https://$githubAccount:$githubToken@github.com/$githubRepository $githubBranch 2>&1");

        JsonResponse::sendSuccess($request);
    }
}
