<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/
$app->post('/webhooks/sportsmed2/jira/{issueKey}/{action}', [
    'as' => 'webhook.jira', 'uses' => 'WebhookController@processSportsMedJiraWebhook'
]);

$app->get('/webhooks/sportsmed2/jira/{issueKey}/{action}', [
    'as' => 'webhook.jira', 'uses' => 'WebhookController@processSportsMedJiraWebhook'
]);


$app->post('/webhooks/sportsmed2/github/{action}', [
    'as' => 'webhook.github', 'uses' => 'WebhookController@processSportsMedGithubWebhook'
]);

$app->get('/webhooks/sportsmed2/github/{action}', [
    'as' => 'webhook.github', 'uses' => 'WebhookController@processSportsMedGithubWebhook'
]);

$app->get('/', [
    'as' => 'home', 'uses' => 'Controller@index'
]);