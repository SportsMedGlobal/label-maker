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
        $user = $this->tools->checkUser($jiraInfo['issue']['fields']['assignee']['name'], $jiraInfo['issue']['fields']['assignee']['displayName']);
        $actionUser = $this->tools->checkUser($jiraInfo['user']['name'], $jiraInfo['user']['displayName']);
        

        foreach ($pullRequests as $pr) {
            if (strpos($pr['title'], $issueKey) !== false) {
                $task = $this->tools->checkTask($issueKey, $jiraInfo['issue']['fields']['summary'], $pr['html_url'], $platform);
                switch ($action) {
                    case 'create_task':
                        if ($request['issue']['fields']['priority']['name'] == 'Critical') {
                            // TODO Send Critical notice
                        }

                        $task->assignee_id = $user->id;
                        $task->state = 'development';
                        $task->save();

                        $this->tools->logAction('created_task', $user->id, $task->id);

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

                        $this->tools->logAction('cr_passed', $user->id, $task->id);

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

                        $this->tools->logAction('cr_failed', $user->id, $task->id);
                        
                        $this->github->addLabel($platform, $pr['number'], 'Status: Revision Needed');
                        $this->github->addLabel($platform, $pr['number'], 'Status: Code Review Needed');
                        $this->github->addLabel($platform, $pr['number'], 'Status: Work In Progress');
                        $this->github->addComment($platform, $pr['number'], '_Code-Monkey (Bot) Says:_ @'.$pr['user']['login']. ' ticket failed code review by: '.$jiraInfo['user']['name'].' on: '. date('Y-m-d H:i') . '');
                        break;

                    case 'testing_in_progress':
                        $task->state = 'in_testing';
                        $task->save();

                        $this->tools->logAction('started_testing', $user->id, $task->id);
                        
                        $this->github->addLabel($platform, $pr['number'], 'Status: In Testing');
                        $this->github->removeLabel($platform, $pr['number'], 'Status: Needs Testing');
                        break;

                    case 'testing_completed':
                        $task->state = 'completed';
                        $task->save();

                        $this->tools->logAction('testing_passed', $user->id, $task->id);
                        
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

                        $this->tools->logAction('testing_failed', $user->id, $task->id);
                        
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

    private function checkTask($issueKey, $summary=null, $prlink = '', $repo='platform')
    {
        $task = Tasks::where('key', $issueKey)->first();
        if (!$task) {
            $task = new Tasks;
            $task->title = $summary;
            $task->key = $issueKey;
            $task->repo_name = $repo;
            $task->pr_link = $prlink;
            $task->save();
        } else {
            $task->repo_name = $repo;
            $task->pr_link = $prlink;
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
