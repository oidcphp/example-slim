<?php

use Illuminate\Container\Container;
use OpenIDConnect\Client;
use OpenIDConnect\Metadata\ClientMetadata;
use OpenIDConnect\Metadata\ProviderMetadata;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$container = new Container();

$container->singleton(Client::class, function() {
    $openIdConnectConfig = require __DIR__ . '/../config.php';

    $provider = new ProviderMetadata(
        $openIdConnectConfig['google']['discovery'],
        $openIdConnectConfig['google']['jwks']
    );

    return new Client($provider, new ClientMetadata([
        'client_id' => '',
        'client_secret' => '',
        'redirect_uri' => '',
    ]));
});

$app = AppFactory::create(null, $container);

$app->get('/', function (RequestInterface $request, ResponseInterface $response) use ($container) {
    $response->getBody()->write('Hi');

    return $response;
});

return $app;
