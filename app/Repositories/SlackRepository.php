<?php

namespace App\Repositories;

class SlackRepository implements SlackInterface
{
    public function sendSlackChannelMessage($message, $channel)
    {
        $slackData = $message;
        $slackData['channel'] = $channel;
        $guzzleClient = new \GuzzleHttp\Client();
        $res = $guzzleClient->request('POST', env('SLACK_API'), [
            'headers' => [
                'Content-type'     => 'application/json'
            ],
            'body' => json_encode($slackData)
        ]);
    }
}