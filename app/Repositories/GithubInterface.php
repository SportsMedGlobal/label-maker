<?php

namespace App\Repositories;

/**
 * Interface SlackInterface
 * @package App\Repositories
 */
interface GithubInterface
{
    public function addLabel($repo, $prNumber, $label);
    public function removeLabel($repo, $prNumber, $label);
    public function addComment($repo, $prNumber, $comment);
    public function getPullRequests($repo);
    public function mergeBranch($repo, $base, $feature);
}