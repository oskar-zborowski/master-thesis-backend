<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class GitHubController extends Controller
{
    /**
     * #### `POST` `/api/v1/github/pull`
     * Zaciągnięcie nowych zmian ze zdalnego repozytorium
     * 
     * @return void
     */
    public function pull(): void {
        echo shell_exec('git pull https://BolleyVall7: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@github.com/BolleyVall7/master-thesis-beckend.git master 2>&1');
    }
}
