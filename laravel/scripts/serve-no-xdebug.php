<?php

putenv('XDEBUG_MODE=off');
$_ENV['XDEBUG_MODE'] = 'off';
$_SERVER['XDEBUG_MODE'] = 'off';

$port = getenv('SERVER_PORT') ?: '8000';
$host = getenv('SERVER_HOST') ?: '127.0.0.1';

$command = implode(' ', [
    escapeshellarg(PHP_BINARY),
    'artisan',
    'serve',
    '--host='.escapeshellarg($host),
    '--port='.escapeshellarg($port),
    '--no-reload',
]);

passthru($command, $exitCode);

exit($exitCode);
