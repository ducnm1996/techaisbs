<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(ExceptionHandler::class)->reportable(function (Throwable $error): void {
    fwrite(STDERR, get_class($error).' at '.$error->getFile().':'.$error->getLine().PHP_EOL);
});
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
file_put_contents($input['ready'], 'ready');
$until = microtime(true) + 15;
while (! file_exists($input['release'])) {
    if (microtime(true) > $until) {
        exit(2);
    } usleep(10000);
}
$server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
if (isset($input['token'])) {
    $server['HTTP_AUTHORIZATION'] = 'Bearer '.$input['token'];
}
$request = Request::create('/api/v1/'.$input['action'], 'POST', [], [], [], $server, json_encode($input['payload'], JSON_THROW_ON_ERROR));
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
config(['database.default' => 'mysql', 'database.connections.mysql' => $input['database']]);
DB::purge('mysql');
$response = $kernel->handle($request);
echo $response->getStatusCode();
$kernel->terminate($request, $response);
