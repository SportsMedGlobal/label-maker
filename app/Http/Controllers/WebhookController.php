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

    public function processSportsMedGithubWebhook(Request $request, $action)
    {
        $platform = 'platform';
        \Log::debug('Github Webhook for old platform');
        $response = $request->all();
        $prNumber = $response['issue']['number'];
        $title = $response['issue']['title'];
        $commentText = $response['comment']['body'];
        $commentId = $response['comment']['id'];
        preg_match('/[A-Z]{2}\-\d+/i', $title, $matches);
        $jiraIssue = $matches[0];


        $platform = 'platform';
        $client = new Client();
        $client->authenticate(env('GITHUB_TOKEN'), '', Client::AUTH_HTTP_TOKEN);
        $comments = $client->api('issue')->comments(); //->all('SportsMedGlobal', $platform);
        $paginator  = new \Github\ResultPager($client);
        $parameters = array('SportsMedGlobal', $platform, $prNumber);
        $allComments     = $paginator->fetchAll($comments, 'all', $parameters);
        $token = base64_encode(env('JIRA_USER').':'.env('JIRA_PASS'));
        $count = 0;
        /*$message = [
            'update' => ['comment' => ['add' => ['body' => 'Passed code review']]],
            'transition' => [
                'id' => '151'
            ]
        ];
        $guzzleClient = new \GuzzleHttp\Client();
        $res = $guzzleClient->request('PUT', 'http://sportsmed.atlassian.net/rest/api/2/issue/'.$jiraIssue.'/transitions', [
            'headers' => [
                'Content-type' => 'application/json',
                'Authorization' => 'Basic '.$token,
            ],
            'body' => json_encode($message)
        ]);
        \Log::info('Jira Response', ['resp' => $res->getStatusCode(), 'message' => json_encode($message), 'jira' => $res->getBody()]);*/
        if (strpos($commentText, 'LGTM') !== false) {
            foreach ($allComments as $comment) {
                if ($comment['id'] !== $commentId) {
                    if (strpos($comment['body'], 'LGTM') !== false) {
                        $count++;
                    }
                }
            }
            if ($count === 1) {
                // Do transition
                $message = [
                    'text' => 'Pull request passed code review. <'.$response['issue']['html_url'].'>',
                    'fallback' => 'Pull request passed code review. <'.$response['issue']['html_url'].'>',
                    'username' => "Code-Monkey", "icon_emoji" => ":monkey_face:",
                    'channel' => '#developers',
                    "fields" => [
                        [
                            'title' => 'Jira Task',
                            'value' => '<https://sportsmed.atlassian.net/browse/'.$jiraIssue.'|'.$jiraIssue.'>'
                        ],
                        [
                            'title' => 'Author',
                            'value' => $response['issue']['user']['login']
                        ],
                        [
                            'title' => 'Pull Request',
                            'value' => '<'.$response['issue']['html_url'].'|'.$prNumber.'>'
                        ],
                    ]
                ];
                $this->sendSlackMessage($message);

            } elseif ($count === 0) {
                // More code reviews needed
                $message = [
                    'text' => 'Pull request awaiting another code review. <'.$response['issue']['html_url'].'>',
                    'fallback' => 'Pull request awaiting another code review. <'.$response['issue']['html_url'].'>',
                    'username' => "Code-Monkey", "icon_emoji" => ":monkey_face:",
                    'channel' => '#developers',
                    "fields" => [
                        [
                            'title' => 'Jira Task',
                            'value' => '<https://sportsmed.atlassian.net/browse/'.$jiraIssue.'|'.$jiraIssue.'>'
                        ],
                        [
                            'title' => 'Author',
                            'value' => $response['issue']['user']['login']
                        ],
                        [
                            'title' => 'Pull Request',
                            'value' => '<'.$response['issue']['html_url'].'|'.$prNumber.'>'
                        ],
                    ]
                ];
                $this->sendSlackMessage($message);
            } else {
                // Check manually
            }
        }


    }

    public function processSportsMedJiraWebhook(Request $request, $issueKey, $action)
    {

        // only support SM and FB tickets for now
        if (strpos($issueKey, 'SM-') !== false) {
            \Log::info('Running old hooks for '. $issueKey);
            return $this->processOldPlatform(json_encode($request->json()), $issueKey, $action);
        } elseif (strpos($issueKey, 'FB-') !== false) {
            \Log::info('Running old hooks for '. $issueKey);
            return $this->processOldPlatform(json_encode($request->json()), $issueKey, $action);
        } elseif (strpos($issueKey, 'PP-') !== false) {
            \Log::info('Running new hooks for '. $issueKey);
            return $this->processNewPlatform(json_encode($request->json()), $issueKey, $action);
        } else {
            \Log::info('Discarding hook for '.$issueKey);
            return 0;
        }
    }

    private function processNewPlatform($request, $issueKey, $action)
    {
        $repos = ['platform3-frontend', 'platform3-backend'];
        $response = json_decode($request, true);
        $pullRequests = [];
        foreach ($repos as $repo) {
            $client = new Client();
            $client->authenticate(env('GITHUB_TOKEN'), '', Client::AUTH_HTTP_TOKEN);
            $openPullRequests = $client->api('pull_request'); //->all('SportsMedGlobal', $platform);
            $paginator  = new \Github\ResultPager($client);
            $parameters = array('SportsMedGlobal', $repo);
            $pullRequests[$repo] = $paginator->fetchAll($openPullRequests, 'all', $parameters);
        }
        $found = false;
        foreach ($pullRequests['platform3-backend'] as $pullRequest) {
            if (strpos($pullRequest['title'], $issueKey) !== false) {
                $found = true;
                $pr = $pullRequest;
                break;
            }
        }

        if (!$found) {
            foreach ($pullRequests['platform3-frontend'] as $pullRequest) {
                if (strpos($pullRequest['title'], $issueKey) !== false) {
                    $found = true;
                    $pr = $pullRequest;
                    break;
                }
            }
        }

        if ($found) {
            switch ($action) {
                case 'code_review_needed':
                    $message = [
                        'text' => 'A new pull request is awaiting a code review. <'.$pr['html_url'].'>',
                        'fallback' => 'A new pull request is awaiting a code review. <'.$pr['html_url'].'>',
                        'username' => "Code-Monkey", "icon_emoji" => ":monkey_face:",
                        'channel' => '#phoenix-development',
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
                    $this->sendSlackMessage($message);
                break;
            }
        }

    }


    private function processOldPlatform($request, $issueKey, $action)
    {

        $platform = 'platform';
        $response = json_decode($request, true);
        $client = new Client();
        $client->authenticate(env('GITHUB_TOKEN'), '', Client::AUTH_HTTP_TOKEN);
        $openPullRequests = $client->api('pull_request'); //->all('SportsMedGlobal', $platform);
        $paginator  = new \Github\ResultPager($client);
        $parameters = array('SportsMedGlobal', $platform);
        $pullRequests     = $paginator->fetchAll($openPullRequests, 'all', $parameters);

        foreach ($pullRequests as $pr) {
            if (strpos($pr['title'], $issueKey) !== false) {
                switch ($action) {
                    case 'dump_gh':
                        echo json_encode($pr);
                        return;
                        break;
                    case 'dump_jira':
                        echo json_encode($response);
                        return;
                        break;
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
                        $this->sendSlackMessage($message);
                        $this->setGithubLabel('add', $platform, $pr['number'], 'Status: Code Review Needed');
                        $this->setGithubLabel('add', $platform, $pr['number'], 'Status: Needs Testing');
                        $this->setGithubLabel('remove', $platform, $pr['number'], 'Status: Revision Needed');
                        break;

                    case 'code_review_done':
                        $message = [
                            'fallback' => 'A new ticket is ready for testing. <https://sportsmed.atlassian.net/browse/'.$issueKey.'>',
                            'text' => 'A new ticket is ready for testing. <https://sportsmed.atlassian.net/browse/'.$issueKey.'>',
                            'username' => "Code-Monkey", "icon_emoji" => ":monkey_face:",
                            'channel' => '#testing',
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
                        $this->sendSlackMessage($message);
                        $this->setGithubLabel('remove', $platform, $pr['number'], 'Status: Revision Needed');
                        $this->setGithubLabel('remove', $platform, $pr['number'], 'Status: Code Review Needed');
                        $this->setGithubLabel('add', $platform, $pr['number'], 'Status: Needs Testing');
                        $this->createGithubComment($platform, $pr['number'], '@'.$pr['user']['login']. ' ticket passed code review on: '. date('r'). ' | This was an automated message');
                        break;

                    case 'code_review_failed':
                        $this->setGithubLabel('add', $platform, $pr['number'], 'Status: Revision Needed');
                        $this->setGithubLabel('add', $platform, $pr['number'], 'Status: Code Review Needed');
                        $this->createGithubComment($platform, $pr['number'], '@'.$pr['user']['login']. ' ticket failed code review on: '. date('r'). ' | This was an automated message');
                        break;

                    case 'testing_in_progress':
                        $this->setGithubLabel('add', $platform, $pr['number'], 'Status: In Testing');
                        $this->setGithubLabel('remove', $platform, $pr['number'], 'Status: Needs Testing');
                        break;

                    case 'testing_completed':
                        $this->setGithubLabel('remove', $platform, $pr['number'], 'Status: Needs Testing');
                        $this->setGithubLabel('remove', $platform, $pr['number'], 'Status: In Testing');
                        $this->setGithubLabel('remove', $platform, $pr['number'], 'Status: Revision Needed');
                        $this->setGithubLabel('add', $platform, $pr['number'], 'Status: Completed');
                        $this->createGithubComment($platform, $pr['number'], '@'.$pr['user']['login']. ' ticket passed testing on: '. date('r'). ' | This was an automated message');
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
                        $this->sendSlackMessage($message);
                        $this->setGithubLabel('add', $platform, $pr['number'], 'Status: Needs Testing');
                        $this->setGithubLabel('remove', $platform, $pr['number'], 'Status: In Testing');
                        $this->setGithubLabel('add', $platform, $pr['number'], 'Status: Revision Needed');
                        $this->createGithubComment($platform, $pr['number'], '@'.$pr['user']['login']. ' ticket failed testing on: '. date('r'). ' | This was an automated message');
                        break;
                }
                break;
            }
        }
        return 1;
    }

    private function setGithubLabel($action, $repo, $number, $label)
    {
        $client = new Client();
        $client->authenticate(env('GITHUB_TOKEN'), '', Client::AUTH_HTTP_TOKEN);
        if ($action === 'add') {
            $labels = $client->api('issue')->labels()->add('SportsMedGlobal', $repo, $number, $label);
        } elseif ($action === 'remove') {
            $labels = $client->api('issue')->labels()->remove('SportsMedGlobal', $repo, $number, $label);
        }
    }

    private function createGithubComment($repo, $number, $comment)
    {
        $client = new Client();
        $client->authenticate(env('GITHUB_TOKEN'), '', Client::AUTH_HTTP_TOKEN);
        $client->api('issue')->comments()->create('SportsMedGlobal', $repo, $number, ['body' => $comment]);
    }

    private function sendSlackMessage($message)
    {
        $guzzleClient = new \GuzzleHttp\Client();
        $res = $guzzleClient->request('POST', env('SLACK_API'), [
            'headers' => [
                'Content-type'     => 'application/json'
            ],
            'body' => json_encode($message)
        ]);
    }



}
