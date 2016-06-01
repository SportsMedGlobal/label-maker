<?php

namespace App\Repositories;

/**
 * Interface JiraInterface
 * @package App\Repositories
 */
interface JiraInterface
{
    public function comment($task, $comment);
}