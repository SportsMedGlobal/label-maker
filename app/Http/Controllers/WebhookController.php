<?php

namespace App\Http\Controllers;

use App\Models\Actions;
use App\Models\Tasks;
use App\Models\Users;
use Carbon\Carbon;
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

    public function processSportsMedJiraWebhook(Request $request, $issueKey, $action)
    {

        // only support SM and FB tickets for now
        if (strpos($issueKey, 'SM-') !== false) {
            \Log::info('Running old '.$action.' hook for '. $issueKey);
            return $this->processOldPlatform($request, $issueKey, $action);
        } elseif (strpos($issueKey, 'FB-') !== false) {
            \Log::info('Running old '.$action.' hook for '. $issueKey);
            return $this->processOldPlatform($request, $issueKey, $action);
        } elseif (strpos($issueKey, 'PP-') !== false) {
            \Log::info('Running new '.$action.' hook for '. $issueKey);
            return $this->processNewPlatform($request, $issueKey, $action);
        } else {
            \Log::info('Discarding '.$action.' hook for '.$issueKey);
            return 0;
        }
    }

    private function processNewPlatform($request, $issueKey, $action)
    {
        $repos = ['platform3-frontend', 'platform3-backend', 'dragoman'];
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

        if (!$found) {
            foreach ($pullRequests['dragoman'] as $pullRequest) {
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
        $jiraInfo = $request->all();
        $client = new Client();
        $client->authenticate(env('GITHUB_TOKEN'), '', Client::AUTH_HTTP_TOKEN);
        $openPullRequests = $client->api('pull_request'); //->all('SportsMedGlobal', $platform);
        $paginator  = new \Github\ResultPager($client);
        $parameters = array('SportsMedGlobal', $platform);
        $pullRequests     = $paginator->fetchAll($openPullRequests, 'all', $parameters);
        $user = $this->checkUser($request['issue']['fields']['assignee']['name'], $request['issue']['fields']['assignee']['displayName']);
        $task = $this->checkTask($issueKey);

        foreach ($pullRequests as $pr) {
            if (strpos($pr['title'], $issueKey) !== false) {
                switch ($action) {
                    case 'create_task':
                        if ($request['issue']['fields']['priority']['name'] == 'Critical') {
                            // TODO Send Critical notice
                        }

                        $task->assignee_id = $user->id;
                        $task->state = 'development';
                        $task->save();

                        $action = new Actions;
                        $action->user_id = $user->id;
                        $action->task_id = $task->id;
                        $action->action = 'created_task';
                        $action->save();

                    break;
                    case 'code_review_needed':
                        $task->assignee_id = $user->id;
                        $task->state = 'needs_cr';
                        $task->save();

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
                                    'title' => 'Task Title',
                                    'value' => $pr['title']
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
                        $this->setGithubLabel('remove', $platform, $pr['number'], 'Status: Work In Progress');
                        break;

                    case 'code_review_done':
                        $task->state = 'needs_testing';
                        $task->save();

                        $action = new Actions;
                        $action->user_id = $user->id;
                        $action->task_id = $task->id;
                        $action->action = 'cr_passed';
                        $action->save();

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
                                    'title' => 'Task Title',
                                    'value' => $pr['title']
                                ],
                                [
                                    'title' => 'Pull Request',
                                    'value' => '<'.$pr['html_url'].'|'.$pr['number'].'>'
                                ]
                            ]
                        ];
                        $this->sendSlackMessage($message);
                        $this->setGithubLabel('remove', $platform, $pr['number'], 'Status: Revision Needed');
                        $this->setGithubLabel('remove', $platform, $pr['number'], 'Status: Code Review Needed');
                        $this->setGithubLabel('add', $platform, $pr['number'], 'Status: Needs Testing');
                        $this->createGithubComment($platform, $pr['number'], '_Code-Monkey (Bot) Says:_ @'.$pr['user']['login']. ' ticket passed code review by: '.$jiraInfo['user']['name'].' on: '. date('Y-m-d H:i') . '');
                        break;

                    case 'code_review_failed':
                        $task->state = 'development';
                        $task->save();

                        $action = new Actions;
                        $action->user_id = $user->id;
                        $action->task_id = $task->id;
                        $action->action = 'cr_failed';
                        $action->save();
                        
                        $this->setGithubLabel('add', $platform, $pr['number'], 'Status: Revision Needed');
                        $this->setGithubLabel('add', $platform, $pr['number'], 'Status: Code Review Needed');
                        $this->setGithubLabel('add', $platform, $pr['number'], 'Status: Work In Progress');
                        $this->createGithubComment($platform, $pr['number'], '_Code-Monkey (Bot) Says:_ @'.$pr['user']['login']. ' ticket failed code review by: '.$jiraInfo['user']['name'].' on: '. date('Y-m-d H:i') . '');
                        break;

                    case 'testing_in_progress':
                        $task->state = 'in_testing';
                        $task->save();

                        $action = new Actions;
                        $action->user_id = $user->id;
                        $action->task_id = $task->id;
                        $action->action = 'started_testing';
                        $action->save();
                        $this->setGithubLabel('add', $platform, $pr['number'], 'Status: In Testing');
                        $this->setGithubLabel('remove', $platform, $pr['number'], 'Status: Needs Testing');
                        break;

                    case 'testing_completed':
                        $task->state = 'completed';
                        $task->save();

                        $action = new Actions;
                        $action->user_id = $user->id;
                        $action->task_id = $task->id;
                        $action->action = 'testing_passed';
                        $action->save();
                        $this->setGithubLabel('remove', $platform, $pr['number'], 'Status: Needs Testing');
                        $this->setGithubLabel('remove', $platform, $pr['number'], 'Status: In Testing');
                        $this->setGithubLabel('remove', $platform, $pr['number'], 'Status: Revision Needed');
                        $this->setGithubLabel('remove', $platform, $pr['number'], 'Status: Work In Progress');
                        $this->setGithubLabel('add', $platform, $pr['number'], 'Status: Completed');
                        $this->createGithubComment($platform, $pr['number'], '_Code-Monkey (Bot) Says:_ @'.$pr['user']['login']. ' ticket passed testing by:'.$jiraInfo['user']['name'].' on: '. date('Y-m-d H:i') . '');
                        break;

                    case 'testing_failed':
                        $task->state = 'development';
                        $task->save();

                        $action = new Actions;
                        $action->user_id = $user->id;
                        $action->task_id = $task->id;
                        $action->action = 'testing_failed';
                        $action->save();
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
                                    'title' => 'Task Title',
                                    'value' => $pr['title']
                                ],
                                [
                                    'title' => 'Pull Request',
                                    'value' => '<'.$pr['html_url'].'|'.$pr['number'].'>'
                                ],
                            ]
                        ];
                        $this->sendSlackMessage($message);
                        $this->setGithubLabel('add', $platform, $pr['number'], 'Status: Code Review Needed');
                        $this->setGithubLabel('add', $platform, $pr['number'], 'Status: Work In Progress');
                        $this->setGithubLabel('add', $platform, $pr['number'], 'Status: Needs Testing');
                        $this->setGithubLabel('remove', $platform, $pr['number'], 'Status: In Testing');
                        $this->setGithubLabel('add', $platform, $pr['number'], 'Status: Revision Needed');
                        $this->createGithubComment($platform, $pr['number'], '_Code-Monkey (Bot) Says:_ @'.$pr['user']['login']. ' ticket failed testing by: '.$jiraInfo['user']['name'].' on: '. date('Y-m-d H:i') . '');
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

    private function checkTask($issueKey)
    {
        $task = Tasks::where('key', $issueKey)->first();
        if (!$task) {
            $task = new Tasks;
            $task->key = $issueKey;
            $task->save();
        }
        return $task;
    }

    private function checkUser($username, $fullName)
    {
        $user = Users::where('username', $username)->first();
        if (!$user) {
            $user = new Users;
            $user->full_name = $fullName;
            $user->username = $username;
            $user->save();
        }
        return $user;
    }



}
