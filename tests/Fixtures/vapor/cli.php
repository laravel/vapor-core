<?php

/*
|--------------------------------------------------------------------------
| Lambda Event Console Commands
|--------------------------------------------------------------------------
|
| Laravel console commands within your app can be executed by Vapor when events
| events from AWS services reach the CLI Lambda. First create a console command
| to handle ab event. Then add the command's signature in this file along with
| a Closure that returns true if the event should be handled by your command.
|
| To utlilize the Lambda event's payload you must add a "--payload" option to
| both the command signature in this file and where you wrote the command.
|
|	return [
|		'command:signature --payload' => function ($event) {
|			return $event['Records'][0]['eventSource'] === 'aws:s3';
|		}
|	];
|
*/

return [
    
    's3:command {payload}' => function ($event) {
        return ($event['Records'][0]['eventSource'] ?? false) === 'aws:s3';
    },

];
