<?php

namespace App\Repositories;

use App\Models\Actions;
use App\Models\Tasks;
use App\Models\Users;

class ToolRepository implements ToolInterface
{
    public function logAction($action ='', $userId, $taskId)
    {
        $action = new Actions();
        $action->user_id = $userId;
        $action->task_id = $taskId;
        $action->action = $action;
        $action->save();
        return $action;
    }

    public function checkTask($issueKey, $summary=null, $prlink = '', $repo='platform')
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

    public function checkUser($username, $fullName)
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