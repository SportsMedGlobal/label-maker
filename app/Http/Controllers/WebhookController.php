<?php

namespace App\Http\Controllers;

use App\Models\Actions;
use App\Models\Tasks;
use App\Models\Users;
use App\Repositories\GithubInterface;
use App\Repositories\SlackInterface;
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
    public function __construct(SlackInterface $slackInterface, GithubInterface $githubInterface)
    {
        $this->slack = $slackInterface;
        $this->github = $githubInterface;
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
            //return $this->processNewPlatform($request, $issueKey, $action);
        } else {
            \Log::info('Discarding '.$action.' hook for '.$issueKey);
            return 0;
        }
    }

    private function processOldPlatform($request, $issueKey, $action)
    {
        $platform = 'platform';
        $jiraInfo = $request->all();
        $pullRequests = $this->github->getPullRequests($platform);
        $user = $this->checkUser($request['issue']['fields']['assignee']['name'], $request['issue']['fields']['assignee']['displayName']);
        $actionUser = $this->checkUser($request['user']['name'], $request['user']['displayName']);
        $task = $this->checkTask($issueKey, $request['issue']['fields']['summary']);

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
                        $this->slack->sendSlackChannelMessage($message, '#developers');
                        $this->github->addLabel($platform, $pr['number'], 'Status: Code Review Needed');
                        $this->github->addLabel($platform, $pr['number'], 'Status: Needs Testing');
                        $this->github->removeLabel($platform, $pr['number'], 'Status: Revision Needed');
                        $this->github->removeLabel($platform, $pr['number'], 'Status: Work In Progress');
                        break;

                    case 'code_review_done':
                        $task->state = 'needs_testing';
                        $task->save();

                        $action = new Actions;
                        $action->user_id = $actionUser->id;
                        $action->task_id = $task->id;
                        $action->action = 'cr_passed';
                        $action->save();

                        $message = [
                            'fallback' => 'A new ticket is ready for testing. <https://sportsmed.atlassian.net/browse/'.$issueKey.'>',
                            'text' => 'A new ticket is ready for testing. <https://sportsmed.atlassian.net/browse/'.$issueKey.'>',
                            'username' => "Code-Monkey", "icon_emoji" => ":monkey_face:",
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
                        $this->slack->sendSlackChannelMessage($message, '#testing');
                        $this->github->addLabel($platform, $pr['number'], 'Status: Needs Testing');
                        $this->github->removeLabel($platform, $pr['number'], 'Status: Code Review Needed');
                        $this->github->removeLabel($platform, $pr['number'], 'Status: Revision Needed');
                        $this->github->addComment($platform, $pr['number'], '_Code-Monkey (Bot) Says:_ @'.$pr['user']['login']. ' ticket passed code review by: '.$jiraInfo['user']['name'].' on: '. date('Y-m-d H:i') . '');
                        break;

                    case 'code_review_failed':
                        $task->state = 'development';
                        $task->save();

                        $action = new Actions;
                        $action->user_id = $actionUser->id;
                        $action->task_id = $task->id;
                        $action->action = 'cr_failed';
                        $action->save();
                        
                        $this->github->addLabel($platform, $pr['number'], 'Status: Revision Needed');
                        $this->github->addLabel($platform, $pr['number'], 'Status: Code Review Needed');
                        $this->github->addLabel($platform, $pr['number'], 'Status: Work In Progress');
                        $this->github->addComment($platform, $pr['number'], '_Code-Monkey (Bot) Says:_ @'.$pr['user']['login']. ' ticket failed code review by: '.$jiraInfo['user']['name'].' on: '. date('Y-m-d H:i') . '');
                        break;

                    case 'testing_in_progress':
                        $task->state = 'in_testing';
                        $task->save();

                        $action = new Actions;
                        $action->user_id = $actionUser->id;
                        $action->task_id = $task->id;
                        $action->action = 'started_testing';
                        $action->save();
                        
                        $this->github->addLabel($platform, $pr['number'], 'Status: In Testing');
                        $this->github->removeLabel($platform, $pr['number'], 'Status: Needs Testing');
                        break;

                    case 'testing_completed':
                        $task->state = 'completed';
                        $task->save();

                        $action = new Actions;
                        $action->user_id = $actionUser->id;
                        $action->task_id = $task->id;
                        $action->action = 'testing_passed';
                        $action->save();
                        
                        $this->github->addLabel($platform, $pr['number'], 'Status: Completed');
                        $this->github->removeLabel($platform, $pr['number'], 'Status: Needs Testing');
                        $this->github->removeLabel($platform, $pr['number'], 'Status: In Testing');
                        $this->github->removeLabel($platform, $pr['number'], 'Status: Revision Needed');
                        $this->github->removeLabel($platform, $pr['number'], 'Status: Work In Progress');
                        $this->github->addComment($platform, $pr['number'], '_Code-Monkey (Bot) Says:_ @'.$pr['user']['login']. ' ticket passed testing by:'.$jiraInfo['user']['name'].' on: '. date('Y-m-d H:i') . '');
                        break;

                    case 'testing_failed':
                        $task->state = 'development';
                        $task->save();

                        $action = new Actions;
                        $action->user_id = $actionUser->id;
                        $action->task_id = $task->id;
                        $action->action = 'testing_failed';
                        $action->save();
                        
                        $message = [
                            'text' => 'The following pull request has failed testing <'.$pr['html_url'].'>',
                            'fallback' => 'The following pull request has failed testing <'.$pr['html_url'].'>',
                            'username' => "Code-Monkey", "icon_emoji" => ":monkey_face:",
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
                        
                        $this->slack->sendSlackChannelMessage($message, '#developers');
                        $this->github->addLabel($platform, $pr['number'], 'Status: Revision Needed');
                        $this->github->addLabel($platform, $pr['number'], 'Status: Code Review Needed');
                        $this->github->addLabel($platform, $pr['number'], 'Status: Work In Progress');
                        $this->github->addLabel($platform, $pr['number'], 'Status: Needs Testing');
                        $this->github->removeLabel($platform, $pr['number'], 'Status: In Testing');
                        $this->github->addComment($platform, $pr['number'], '_Code-Monkey (Bot) Says:_ @'.$pr['user']['login']. ' ticket failed testing by: '.$jiraInfo['user']['name'].' on: '. date('Y-m-d H:i') . '');
                        break;
                }
                break;
            }
        }
        return 1;
    }

    private function checkTask($issueKey, $summary=null)
    {
        $task = Tasks::where('key', $issueKey)->first();
        if (!$task) {
            $task = new Tasks;
            $task->title = $summary;
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
