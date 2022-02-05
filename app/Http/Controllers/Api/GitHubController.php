<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

/**
 * Klasa odpowiedzialna za komunikację z serwisem GitHub
 */
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