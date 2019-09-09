<?php

use Dotenv\Dotenv;
use Illuminate\Container\Container;
use Illuminate\Support\Str;
use OpenIDConnect\Client;
use OpenIDConnect\Metadata\ClientMetadata;
use OpenIDConnect\Metadata\ProviderMetadata;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Factory\UriFactory;

require __DIR__ . '/../vendor/autoload.php';

session_start();

Dotenv::create(__DIR__ . '/../')->load();

$container = new Container();

$container->singleton(StreamFactoryInterface::class, StreamFactory::class);
$container->singleton(ResponseFactoryInterface::class, ResponseFactory::class);
$container->singleton(UriFactoryInterface::class, UriFactory::class);
$container->singleton(\GuzzleHttp\ClientInterface::class, \GuzzleHttp\Client::class);

$container->singleton(Client::class, function (Container $container) {
    $openIdConnectConfig = require __DIR__ . '/../config.php';

    $provider = new ProviderMetadata(
        $openIdConnectConfig['google']['discovery'],
        $openIdConnectConfig['google']['jwks']
    );

    return new Client($provider, new ClientMetadata([
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
    ]), $container);
});

$app = AppFactory::create(null, $container);

$app->get('/', function (RequestInterface $request, ResponseInterface $response) {
    $response->getBody()->write('Hi');

    return $response;
});

$app->get('/login-post', function (RequestInterface $request, ResponseInterface $response) use ($container) {
    /** @var Client $client */
    $client = $container->make(Client::class);

    $state = Str::random();

    $_SESSION['state'] = $state;

    return $client->createAuthorizeFormPostResponse([
        'response_type' => 'code',
        'scope' => 'openid profile email',
        'state' => $state,
    ]);
});

$app->get('/login-get', function (RequestInterface $request, ResponseInterface $response) use ($container) {
    /** @var Client $client */
    $client = $container->make(Client::class);

    $state = Str::random();

    $_SESSION['state'] = $state;

    return $client->createAuthorizeFormPostResponse([
        'response_type' => 'code',
        'scope' => 'openid profile email',
        'state' => $state,
    ]);
});

$app->get('/callback', function (RequestInterface $request, ResponseInterface $response) use ($container) {
    /** @var Client $client */
    $client = $container->make(Client::class);

    parse_str($request->getUri()->getQuery(), $query);

    $token = $client->handleOpenIDConnectCallback($query, [
        'state' => $_SESSION['state'],
    ]);

    dump($token->jsonSerialize());

    dump($token->idTokenClaims()->all());

    return $response;
});

return $app;
