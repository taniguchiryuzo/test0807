<?php

namespace App\Services;

use Illuminate\Http\Request;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;
use LINE\LINEBot;

class LineBotService
{
    /**
     * @var CurlHTTPClient
     */
    protected $httpClient;

    /**
     * @var LINEBot
     */
    protected $bot;

    public function __construct()
    {
        $this->httpClient = new CurlHTTPClient('<channel access token>');
        $this->bot = new LINEBot($this->httpClient, ['channelSecret' => '<channel secret>']);
    }

    /**
     * Reply based on the message sent to LINE.
     * LINEに送信されたメッセージをもとに返信する
     *
     * @param Request
     * @return int
     * @throws \LINE\LINEBot\Exception\InvalidSignatureException
     */
    public function eventHandler(Request $request): int
    {
        return 200;
    }
}
