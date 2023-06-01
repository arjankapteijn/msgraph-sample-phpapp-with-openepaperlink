<?php
// Copyright (c) Microsoft Corporation.
// Licensed under the MIT License.

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Cache;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    // <LoadViewDataSnippet>
    public function loadViewData()
    {
        $viewData = [];

        // Check for flash errors
        if (session('error')) {
            $viewData['error'] = session('error');
            $viewData['errorDetail'] = session('errorDetail');
        }

        //dd(cache('userName'));

        // Check for logged on user
        if (Cache::has('userName'))
        {
            $viewData['userName'] = Cache::get('userName');
            $viewData['userEmail'] = Cache::get('userEmail');
            $viewData['userTimeZone'] = Cache::get('userTimeZone');
        }

        return $viewData;
    }
    // </LoadViewDataSnippet>
}
