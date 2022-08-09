<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogController extends Controller
{
    //
    public function index(Request $request)
    {
        Log::info('infoログです。');

        return "Logを出力しました";
    }
}
