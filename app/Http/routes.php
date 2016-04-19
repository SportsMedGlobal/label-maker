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

$app->get('/', function () use ($app) {
    return $app->version();
});

$app->post('/webhooks/sportsmed2/jira/{issueKey}/{action}/{platform}', [
    'as' => 'webhook', 'uses' => 'WebhookController@processSportsMedJiraWebhook'
]);

$app->get('/webhooks/sportsmed2/jira/{issueKey}/{action}/{platform}', [
    'as' => 'webhook', 'uses' => 'WebhookController@processSportsMedJiraWebhook'
]);