<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\LineBotService;

class LineBotController extends Controller
{
    /**
     * @var LineBotService
     */
    protected $line_bot_service;

    public function __construct()
    {
        $this->line_bot_service = new LineBotService();
    }

    /**
     * When a message is sent to the official Line account,
     * The API is called by LINE WebHook and this method is called.
     *
     * Lineの公式アカウントにメッセージが送られたときに
     * LINE Web HookにてAPIがCallされこのメソッドが呼ばれる
     *
     * @param Request
     */
    public function reply(Request $request)
    {
        // Requestが来たかどうか確認する
        $content = 'Request from LINE';
        $param_str = json_encode($request->all());
        $log_message =
            <<<__EOM__
        $content
        $param_str
        __EOM__;

        \Log::debug($log_message);

        $status_code = $this->line_bot_service->eventHandler($request);

        return response('', $status_code, []);
    }
}
