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
        // $this->httpClient = new CurlHTTPClient('<channel access token>');
        // $this->bot = new LINEBot($this->httpClient, ['channelSecret' => '<channel secret>']);
        $this->httpClient = new CurlHTTPClient(config('app.line_channel_access_token'));
        $this->bot = new LINEBot($this->httpClient, ['channelSecret' => config('app.line_channel_secret')]);
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
        $this->validateSignature($request);

        return 200;
    }

    /**
     * LINEの署名確認
     *
     * @param Request
     * @return void
     * @throws HttpException
     */
    public function validateSignature(Request $request): void
    {
        // リクエストヘッダーについてくる実際の署名
        $signature = $request->header('x-line-signature');
        if ($signature === null) {
            abort(400);
        }

        // LINEチャネルシークレットとリクエストボディを基に署名を生成
        $hash = hash_hmac('sha256', $request->getContent(), config('app.line_channel_secret'), true);
        $expect_signature = base64_encode($hash);

        // 実際の署名と生成した署名が同じであれば検証OK
        if (!hash_equals($expect_signature, $signature)) {
            abort(400);
        }
    }
}
