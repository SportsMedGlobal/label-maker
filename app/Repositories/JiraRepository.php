<?php

namespace App\Repositories;

class JiraRepository implements JiraInterface
{
    public function comment($task, $comment)
    {
        $ch = curl_init();
        $headers = [];
        $headers[] = 'Authorization: Basic '.base64_encode(env('JIRA_USER') . ':' . env('JIRA_PASS'));
        $headers[] = 'Content-Type: application/json';

        $data = ['body' => $comment];
        $params = json_encode($data);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_URL, 'https://sportsmed.atlassian.net/rest/api/2/issue/' . $task . '/comment');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 0);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_exec($ch);
        curl_close($ch);
    }
}