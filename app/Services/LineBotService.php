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

    public function eventHandler(Request $request): int
    {
        // return 200;

        // 署名を検証しLINE以外からのリクエストを受け付けない。
        $this->validateSignature($request);

        // リクエストをEventオブジェクトに変換する
        $events = $this->bot->parseEventRequest($request->getContent(), $request->header('x-line-signature'));

        foreach ($events as $event) {
            // Reply token無しでは返信できないため定義しておく
            $reply_token = $event->getReplyToken();
            // 無効な操作があったときに送るメッセージ
            $message_builder = new TextMessageBuilder('Invalid operation. 無効な操作です。');
            // アクションした人のLINEのユーザーID
            $line_user_id = $event->getUserId();

            switch (true) {
                    // テキストメッセージを受信した場合
                case $event instanceof TextMessage:
                    break;
                    // 選択肢を選んだ場合
                case $event instanceof PostbackEvent:
                    break;
            }
        }

        // LINEに返信
        $response = $this->bot->replyMessage($reply_token, $message_builder);

        // 送信に失敗したらログに吐いておく
        if (!$response->isSucceeded()) {
            \Log::error('Failed!' . $response->getHTTPStatus() . ' ' . $response->getRawBody());
        }

        return $response->getHTTPStatus();
    }
}
