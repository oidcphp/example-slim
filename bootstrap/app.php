<?php

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

$app->get('/', function (RequestInterface $request, ResponseInterface $response) {
    $response->getBody()->write('Hi');

    return $response;
});

return $app;