<?php

namespace App\Repositories;

/**
 * Interface SlackInterface
 * @package App\Repositories
 */
interface SlackInterface
{
    public function sendSlackMessage($message, $channel);
}