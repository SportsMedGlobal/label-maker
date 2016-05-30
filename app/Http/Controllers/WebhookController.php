<?php

namespace App\Http\Controllers;

use App\Models\Actions;
use App\Models\Tasks;
use App\Models\Users;
use App\Repositories\GithubInterface;
use App\Repositories\SlackInterface;
use App\Repositories\ToolInterface;
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
    public function __construct(SlackInterface $slackInterface, GithubInterface $githubInterface, ToolInterface $toolInterface)
    {
        $this->slack = $slackInterface;
        $this->github = $githubInterface;
        $this->tools = $toolInterface;
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
        $assignedUser = $this->tools->checkUser($jiraInfo['issue']['fields']['assignee']['name'], $jiraInfo['issue']['fields']['assignee']['displayName']);
        $actionUser = $this->tools->checkUser($jiraInfo['user']['name'], $jiraInfo['user']['displayName']);

        foreach ($pullRequests as $pr) {
            if (strpos($pr['title'], $issueKey) !== false) {
                $task = $this->tools->checkTask($issueKey, $jiraInfo['issue']['fields']['summary'], $pr['html_url'], $platform);
                switch ($action) {
                    case 'create_task':
                        if ($request['issue']['fields']['priority']['name'] == 'Critical') {
                            // TODO Send Critical notice
                        }

                        if (empty($task->assignee_id)) {
                            $task->assignee_id = $assignedUser->id;
                        }
                        $task->state = 'development';
                        $task->save();

                        $this->tools->logAction('created_task', $actionUser->id, $task->id);

                    break;
                    case 'code_review_needed':
                        if (empty($task->assignee_id)) {
                            $task->assignee_id = $assignedUser->id;
                        }
                        $task->state = 'needs_cr';
                        $task->save();

                        $message = [
                            'text' => 'A new pull request is awaiting a code review. <'.$pr['html_url'].'>',
                            'fallback' => 'A new pull request is awaiting a code review. <'.$pr['html_url'].'>',
                            'username' => "CodeMonkey", "icon_emoji" => ":monkey_face:",
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
                        $this->slack->sendSlackMessage($message, '#developers');
                        $this->github->addLabel($platform, $pr['number'], 'Status: Code Review Needed');
                        $this->github->addLabel($platform, $pr['number'], 'Status: Needs Testing');
                        $this->github->removeLabel($platform, $pr['number'], 'Status: Revision Needed');
                        $this->github->removeLabel($platform, $pr['number'], 'Status: Work In Progress');
                        break;

                    case 'code_review_done':
                        $task->state = 'needs_testing';
                        $task->save();

                        $this->tools->logAction('cr_passed', $actionUser->id, $task->id);

                        $message = [
                            'fallback' => 'A new ticket is ready for testing. <https://sportsmed.atlassian.net/browse/'.$issueKey.'>',
                            'text' => 'A new ticket is ready for testing. <https://sportsmed.atlassian.net/browse/'.$issueKey.'>',
                            'username' => "CodeMonkey", "icon_emoji" => ":monkey_face:",
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
                        $this->slack->sendSlackMessage($message, '#testing');
                        $this->github->addLabel($platform, $pr['number'], 'Status: Needs Testing');
                        $this->github->removeLabel($platform, $pr['number'], 'Status: Code Review Needed');
                        $this->github->removeLabel($platform, $pr['number'], 'Status: Revision Needed');
                        $this->github->addComment($platform, $pr['number'], '_CodeMonkey (Bot) Says:_ @'.$pr['user']['login']. ' ticket passed code review by: '.$actionUser->username.' on: '. date('Y-m-d H:i') . '');
                        break;

                    case 'code_review_failed':
                        $task->state = 'development';
                        $task->save();

                        $this->tools->logAction('cr_failed', $actionUser->id, $task->id);

                        try {
                            $taskUser = Users::findOrFail($task->assignee_id);
                            if (!empty($taskUser->slack_handle)) {
                                $message = [
                                    'text' => 'Your pull request has failed Code Review <'.$task->pr_link.'>',
                                    'fallback' => 'Your pull request has failed Code Review <'.$task->pr_link.'>',
                                    'username' => "CodeMonkey", "icon_emoji" => ":monkey_face:",
                                    "fields" => [
                                        [
                                            'title' => 'Jira Task',
                                            'value' => '<https://sportsmed.atlassian.net/browse/'.$issueKey.'|'.$issueKey.'>'
                                        ],
                                        [
                                            'title' => 'Task Title',
                                            'value' => $task->title
                                        ],
                                        [
                                            'title' => 'Pull Request',
                                            'value' => '<' . $task->pr_link.'>'
                                        ],
                                    ]
                                ];

                                $this->slack->sendSlackMessage($message, '@'.$taskUser->slack_handle);
                            }
                        } catch (\Exception $e) {
                            // Do nothing
                        }

                        $this->github->addLabel($platform, $pr['number'], 'Status: Revision Needed');
                        $this->github->addLabel($platform, $pr['number'], 'Status: Work In Progress');
                        $this->github->removeLabel($platform, $pr['number'], 'Status: Code Review Needed');
                        $this->github->removeLabel($platform, $pr['number'], 'Status: Needs Testing');
                        $this->github->addComment($platform, $pr['number'], '_CodeMonkey (Bot) Says:_ @'.$pr['user']['login']. ' ticket failed code review by: '.$actionUser->username.' on: '. date('Y-m-d H:i') . '');
                        break;

                    case 'testing_in_progress':
                        $task->state = 'in_testing';
                        $task->save();

                        $this->tools->logAction('started_testing', $actionUser->id, $task->id);
                        
                        $this->github->addLabel($platform, $pr['number'], 'Status: In Testing');
                        $this->github->removeLabel($platform, $pr['number'], 'Status: Needs Testing');
                        break;

                    case 'testing_completed':
                        $task->state = 'completed';
                        $task->completed_at = Carbon::now()->toDateTimeString();
                        $task->save();

                        $this->tools->logAction('testing_passed', $actionUser->id, $task->id);
                        
                        $this->github->addLabel($platform, $pr['number'], 'Status: Completed');
                        $this->github->removeLabel($platform, $pr['number'], 'Status: Needs Testing');
                        $this->github->removeLabel($platform, $pr['number'], 'Status: In Testing');
                        $this->github->removeLabel($platform, $pr['number'], 'Status: Revision Needed');
                        $this->github->removeLabel($platform, $pr['number'], 'Status: Work In Progress');
                        $this->github->removeLabel($platform, $pr['number'], 'Status: Code Review Needed');
                        $this->github->addComment($platform, $pr['number'], '_CodeMonkey (Bot) Says:_ @'.$pr['user']['login']. ' ticket passed testing by:'.$actionUser->username.' on: '. date('Y-m-d H:i') . '');
                        break;

                    case 'testing_failed':
                        $task->state = 'development';
                        $task->save();

                        $this->tools->logAction('testing_failed', $actionUser->id, $task->id);

                        try {
                            $taskUser = Users::findOrFail($task->assignee_id);
                            if (!empty($taskUser->slack_handle)) {
                                $message = [
                                    'text' => 'Your pull request has failed testing <'.$task->pr_link.'>',
                                    'fallback' => 'Your pull request has failed testing <'.$task->pr_link.'>',
                                    'username' => "CodeMonkey", "icon_emoji" => ":monkey_face:",
                                    "fields" => [
                                        [
                                            'title' => 'Jira Task',
                                            'value' => '<https://sportsmed.atlassian.net/browse/'.$issueKey.'|'.$issueKey.'>'
                                        ],
                                        [
                                            'title' => 'Task Title',
                                            'value' => $pr['title']
                                        ],
                                        [
                                            'title' => 'Pull Request',
                                            'value' => '<' . $task->pr_link.'>'
                                        ],
                                    ]
                                ];

                                $this->slack->sendSlackMessage($message, '@'.$taskUser->slack_handle);
                            }
                        } catch (\Exception $e) {
                            // Do nothing
                        }
                        
                        $message = [
                            'text' => 'The following pull request has failed testing <'.$pr['html_url'].'>',
                            'fallback' => 'The following pull request has failed testing <'.$pr['html_url'].'>',
                            'username' => "CodeMonkey", "icon_emoji" => ":monkey_face:",
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
                        
                        $this->slack->sendSlackMessage($message, '#developers');
                        $this->github->addLabel($platform, $pr['number'], 'Status: Revision Needed');
                        $this->github->removeLabel($platform, $pr['number'], 'Status: Code Review Needed');
                        $this->github->addLabel($platform, $pr['number'], 'Status: Work In Progress');
                        $this->github->removeLabel($platform, $pr['number'], 'Status: Needs Testing');
                        $this->github->removeLabel($platform, $pr['number'], 'Status: In Testing');
                        $this->github->addComment($platform, $pr['number'], '_CodeMonkey (Bot) Says:_ @'.$pr['user']['login']. ' ticket failed testing by: '.$jiraInfo['user']['name'].' on: '. date('Y-m-d H:i') . '');
                        break;
                }
                break;
            }
        }
        return 1;
    }
}
