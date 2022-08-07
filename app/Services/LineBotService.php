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

        // return 200;


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
                    // "pick news type"と送信された場合
                    if ($event->getText() === 'pick news type') {
                        // 今までの回答をリセット
                        $this->answer_model->resetStep($line_user_id);
                        // 国選択メッセージを定義
                        $message_builder = $this->buildStep0Msg();
                        // 次のステップに進んだことを示すフラグを立てておく
                        $this->answer_model->storeNextStep($line_user_id, 0);
                    }
                    break;
                    // 選択肢を選んだ場合
                case $event instanceof PostbackEvent:
                    // 回答を定義
                    $postback_answer = $event->getPostbackData();
                    // 未回答のレコードを取得
                    $current_answer = $this->answer_model->latest()->where('answer', '')->first();

                    switch ($current_answer->step) {
                        case 0: // 言語選択時 selected language
                            // 回答をDBに保存
                            $this->answer_model->storeAnswer($current_answer, $postback_answer);

                            // 次のステップに進んだことを示すフラグを立てておく
                            $this->answer_model->storeNextStep($line_user_id, 1);

                            // 次のメッセージを生成する
                            $message_builder = $this->buildStep1Msg();
                            break;

                        case 1: // 国選択時 selected country
                            $this->answer_model->storeAnswer($current_answer, $postback_answer);

                            $this->answer_model->storeNextStep($line_user_id, 2);

                            $message_builder = $this->buildStep2Msg();
                            break;
                        default:
                            break;

                        case 2: // カテゴリ選択時 selected category(end)
                            $this->answer_model->storeAnswer($current_answer, $postback_answer);

                            // Step 0 ~ 2までの回答を取得
                            $answers = $this->answer_model->where('line_user_id', $line_user_id)->get();

                            // それぞれ定義
                            $category = $answers->whereStrict('step', 2)->first()->answer;
                            $language = $answers->whereStrict('step', 0)->first()->answer;
                            $country = $answers->whereStrict('step', 1)->first()->answer;
                            // ニュースを取得
                            $news = $this->newsapi_client->getSources($category, $language, $country);

                            // 取得したニュースを基に結果メッセージを生成
                            $message_builder = $this->buildResultMsg($news->sources);
                            break;
                        default:
                            # code...
                            break;
                    }
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
    /**
     * Step0用のTemplateMessageBuilderを生成する
     * @param void
     * @return TemplateMessageBuilder
     */
    public function buildStep0Msg(): TemplateMessageBuilder
    {
        return new TemplateMessageBuilder(
            "Select Language / 言語選択", // チャット一覧に表示される
            new ConfirmTemplateBuilder(
                "Select Language / 言語選択", // title
                [
                    new PostbackTemplateActionBuilder("Engish", "en"), // option
                    new PostbackTemplateActionBuilder("French", "fr"), // option
                ]
            )
        );
    }

    /**
     * Return TemplateMessageBuilder for step1.
     * Step1用のTemplateMessageBuilderを生成する
     * @param void
     * @return TemplateMessageBuilder
     */
    public function buildStep1Msg(): TemplateMessageBuilder
    {
        return new TemplateMessageBuilder(
            "Which country do you watch the news for?", // チャットルーム一覧に表示される
            new ButtonTemplateBuilder(
                "Which country do you watch the news for?", // メッセージのタイトル
                "Select A Country / 国選択", // メッセージの内容 
                "",
                [
                    new PostbackTemplateActionBuilder("United States", "us"), // 選択肢
                    new PostbackTemplateActionBuilder("Japan", "jp"), // 選択肢
                    new PostbackTemplateActionBuilder("Canada", "ca"), // 選択肢
                ]
            )
        );
    }
    /**
     * Return TemplateMessageBuilder for step2.
     * Step2用のTemplateMessageBuilderを生成する
     * @param void
     * @return TemplateMessageBuilder
     */
    public function buildStep2Msg(): TemplateMessageBuilder
    {
        return new TemplateMessageBuilder(
            "Which category?",
            new ButtonTemplateBuilder(
                "Which category?",
                "Select A Category / カテゴリ選択",
                "",
                [
                    new PostbackTemplateActionBuilder("Business", "business"),
                    new PostbackTemplateActionBuilder("General", "general"),
                    new PostbackTemplateActionBuilder("Science", "science"),
                    new PostbackTemplateActionBuilder("Tech", "technology"),
                ]
            )
        );
    }
    /**
     * ニュース取得結果のTemplateMessageBuilderを生成する
     * @param array
     * @return TemplateMessageBuilder|TextMessageBuilder
     */
    public function buildResultMsg(array $sources): mixed
    {
        // Newsデータがない場合
        if (empty($sources)) {
            return new TextMessageBuilder('No result / ニュースがありませんでした');
        } else {
            $columns = [];
            // 5個までアイテムを生成する
            foreach ($sources as $num => $source) {
                if ($num > 4) {
                    break;
                }

                // バックスラッシュ削除
                // httpsにする必要がある
                $replacement = [
                    '\\' => '',
                    'http:' => 'https:'
                ];

                // 置換
                $url = str_replace(
                    array_keys($replacement),
                    array_values($replacement),
                    $source->url
                );

                // URL部分を定義(ボタン部分)
                $link = new UriTemplateActionBuilder('See This News', $url);
                // アスペクト比を適当に変えてそれぞれ違う画像にする
                $acp = $num * 200 === 0 ? 100 : $num * 200;
                // アイテムをColumnとして配列に入れておく
                $columns[] = new CarouselColumnTemplateBuilder(
                    $source->name, // Title
                    mb_strimwidth($source->description, 0, 59, "...", 'UTF-8'), // 60文字までOK
                    "https://placeimg.com/640/$acp/tech", // ランダムな画像のURL
                    [$link] // 先程のURL部分
                );
            }

            // カラムをカルーセルに組み込む
            $carousel = new CarouselTemplateBuilder($columns, 'square');

            return new TemplateMessageBuilder("News results", $carousel);
        }
    }
}
