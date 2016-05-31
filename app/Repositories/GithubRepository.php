<?php
namespace App\Repositories;

use Github\Client;

class GithubRepository implements GithubInterface
{
    public function addLabel($repo, $prNumber, $label)
    {
        $client = new Client();
        $client->authenticate(env('GITHUB_TOKEN'), '', Client::AUTH_HTTP_TOKEN);
        $client->api('issue')->labels()->add('SportsMedGlobal', $repo, $prNumber, $label);
    }

    public function removeLabel($repo, $prNumber, $label)
    {
        $client = new Client();
        $client->authenticate(env('GITHUB_TOKEN'), '', Client::AUTH_HTTP_TOKEN);
        $client->api('issue')->labels()->remove('SportsMedGlobal', $repo, $prNumber, $label);
    }

    public function addComment($repo, $prNumber, $comment)
    {
        $client = new Client();
        $client->authenticate(env('GITHUB_TOKEN'), '', Client::AUTH_HTTP_TOKEN);
        $client->api('issue')->comments()->create('SportsMedGlobal', $repo, $prNumber, ['body' => $comment]);
    }
    
    public function getPullRequests($repo)
    {
        $client = new Client();
        $client->authenticate(env('GITHUB_TOKEN'), '', Client::AUTH_HTTP_TOKEN);
        $openPullRequests = $client->api('pull_request'); //->all('SportsMedGlobal', $platform);
        $paginator  = new \Github\ResultPager($client);
        $parameters = ['SportsMedGlobal', $repo];
        $pullRequests     = $paginator->fetchAll($openPullRequests, 'all', $parameters);
        return $pullRequests;
    }
    
    public function mergeBranch($repo, $base, $feature)
    {
        $client = new Client();
        $client->authenticate(env('GITHUB_TOKEN'), '', Client::AUTH_HTTP_TOKEN);
        return $client->api('repo')->merge('SportsMedGlobal', $repo, $base, $feature, 'Merged ' . $feature . ' into '. $base);
    }
}