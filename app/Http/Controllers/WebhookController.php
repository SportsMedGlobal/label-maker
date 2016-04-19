<?php

namespace App\Http\Controllers;

use Github\Client;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function processJiraWebhook(Request $request, $issueKey, $action, $platform)
    {
        $response = json_decode($request->json(), true);
        $client = new Client();
        $client->authenticate(env('GITHUB_TOKEN'), '', Client::AUTH_HTTP_TOKEN);
        $openPullRequests = $client->api('pull_request'); //->all('SportsMedGlobal', $platform);
        $paginator  = new \Github\ResultPager($client);
        $parameters = array('SportsMedGlobal', $platform);
        $pullRequests     = $paginator->fetchAll($openPullRequests, 'all', $parameters);

        foreach ($pullRequests as $pr) {
            if (strpos($pr['title'], $issueKey) !== false) {
                switch ($action) {
                    case 'code_review_needed':
                        $message = [
                            'text' => 'A new pull request is awaiting a code review. <'.$pr['html_url'].'>',
                            'fallback' => 'A new pull request is awaiting a code review. <'.$pr['html_url'].'>',
                            'username' => "Code-Monkey", "icon_emoji" => ":monkey_face:",
                            'channel' => '#developers',
                            "fields" => [
                                [
                                    'title' => 'Jira Task',
                                    'value' => '<https://sportsmed.atlassian.net/browse/'.$issueKey.'|'.$issueKey.'>'
                                ],
                                [
                                    'title' => 'Author',
                                    'value' => $pr['user']['login']
                                ],
                                [
                                    'title' => 'Pull Request',
                                    'value' => '<'.$pr['html_url'].'|'.$pr['number'].'>'
                                ],
                            ]
                        ];
                        $guzzleClient = new \GuzzleHttp\Client();
                        $res = $guzzleClient->request('POST', env('SLACK_API'), [
                            'headers' => [
                                'Content-type'     => 'application/json'
                            ],
                            'body' => json_encode($message)
                        ]);
                        if ($response['issue']['fields']['issuetype']['name'] === 'Bug') {
                            $this->attachLabel('add', $platform, $pr['number'], 'Type: Bug');
                            $this->attachLabel('remove', $platform, $pr['number'], 'Type: Enhancement');
                        } else {
                            $this->attachLabel('add', $platform, $pr['number'], 'Type: Enhancement');
                            $this->attachLabel('remove', $platform, $pr['number'], 'Type: Bug');
                        }
                        $this->attachLabel('add', $platform, $pr['number'], 'Status: Code Review Needed');
                    break;

                    case 'code_review_done':
                        $message = [
                            'fallback' => 'A new ticket is ready for testing. <https://sportsmed.atlassian.net/browse/'.$issueKey.'>',
                            'text' => 'A new ticket is ready for testing. <https://sportsmed.atlassian.net/browse/'.$issueKey.'>',
                            'username' => "Code-Monkey", "icon_emoji" => ":monkey_face:",
                            'channel' => '#testers',
                            "fields" => [
                                [
                                    'title' => 'Jira Task',
                                    'value' => '<https://sportsmed.atlassian.net/browse/'.$issueKey.'|'.$issueKey.'>'
                                ],
                                [
                                    'title' => 'Author',
                                    'value' => $pr['user']['login']
                                ],
                                [
                                    'title' => 'Pull Request',
                                    'value' => '<'.$pr['html_url'].'|'.$pr['number'].'>'
                                ],
                            ]
                        ];
                        $guzzleClient = new \GuzzleHttp\Client();
                        $res = $guzzleClient->request('POST', env('SLACK_API'), [
                            'headers' => [
                                'Content-type'     => 'application/json'
                            ],
                            'body' => json_encode($message)
                        ]);
                        $this->attachLabel('remove', $platform, $pr['number'], 'Status: Revision Needed');
                        $this->attachLabel('remove', $platform, $pr['number'], 'Status: Code Review Needed');
                        $this->attachLabel('add', $platform, $pr['number'], 'Status: Needs Testing');
                    break;

                    case 'code_review_failed':
                        $this->attachLabel('add', $platform, $pr['number'], 'Status: Revision Needed');
                        $this->attachLabel('add', $platform, $pr['number'], 'Status: Code Review Needed');
                    break;

                    case 'testing_in_progress':
                        $this->attachLabel('add', $platform, $pr['number'], 'Status: In Testing');
                        $this->attachLabel('remove', $platform, $pr['number'], 'Status: Needs Testing');
                    break;

                    case 'testing_completed':
                        $this->attachLabel('remove', $platform, $pr['number'], 'Status: Needs Testing');
                        $this->attachLabel('remove', $platform, $pr['number'], 'Status: In Testing');
                        $this->attachLabel('remove', $platform, $pr['number'], 'Status: Revision Needed');
                        $this->attachLabel('add', $platform, $pr['number'], 'Status: Completed');
                    break;

                    case 'testing_failed':
                        $message = [
                            'text' => 'The following pull request has failed testing <'.$pr['html_url'].'>',
                            'fallback' => 'The following pull request has failed testing <'.$pr['html_url'].'>',
                            'username' => "Code-Monkey", "icon_emoji" => ":monkey_face:",
                            'channel' => '#developers',
                            "fields" => [
                                [
                                    'title' => 'Jira Task',
                                    'value' => '<https://sportsmed.atlassian.net/browse/'.$issueKey.'|'.$issueKey.'>'
                                ],
                                [
                                    'title' => 'Author',
                                    'value' => $pr['user']['login']
                                ],
                                [
                                    'title' => 'Pull Request',
                                    'value' => '<'.$pr['html_url'].'|'.$pr['number'].'>'
                                ],
                            ]
                        ];
                        $guzzleClient = new \GuzzleHttp\Client();
                        $res = $guzzleClient->request('POST', env('SLACK_API'), [
                            'headers' => [
                                'Content-type'     => 'application/json'
                            ],
                            'body' => json_encode($message)
                        ]);
                        $this->attachLabel('add', $platform, $pr['number'], 'Status: Needs Testing');
                        $this->attachLabel('remove', $platform, $pr['number'], 'Status: In Testing');
                        $this->attachLabel('add', $platform, $pr['number'], 'Status: Revision Needed');
                    break;
                }
            }
        }
        return;
    }


    private function attachLabel($action, $repo, $number, $label)
    {
        $client = new Client();
        $client->authenticate(env('GITHUB_TOKEN'), '', Client::AUTH_HTTP_TOKEN);
        if ($action === 'add') {
            $labels = $client->api('issue')->labels()->add('SportsMedGlobal', $repo, $number, $label);
        } elseif ($action === 'remove') {
            $labels = $client->api('issue')->labels()->remove('SportsMedGlobal', $repo, $number, $label);
        }
    }



}
