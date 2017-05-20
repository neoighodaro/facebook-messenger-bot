<?php

$app->get('/webhook', 'BotController@verify_token');
$app->post('/webhook', 'BotController@handle_query');

$app->get('/', function () {
    return response('Holla!');
});