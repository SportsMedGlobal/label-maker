<?php

namespace App\Repositories;

/**
 * Interface SlackInterface
 * @package App\Repositories
 */
interface ToolInterface
{
    public function logAction($action, $userId, $taskId);
    public function checkTask($issueKey, $summary=null, $prlink = '', $repo='platform');
    public function checkUser($username, $fullName);
}