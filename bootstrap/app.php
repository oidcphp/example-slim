<?php

use Dotenv\Dotenv;
use Illuminate\Container\Container;
use Illuminate\Support\Str;
use MilesChou\Psr\Http\Client\HttpClientInterface;
use MilesChou\Psr\Http\Client\HttpClientManager;
use OpenIDConnect\Client;
use OpenIDConnect\Config as OpenIdConnectConfig;
use OpenIDConnect\Issuer;
use OpenIDConnect\Metadata\ClientMetadata;
use OpenIDConnect\Metadata\ProviderMetadata;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\ClientInterface as Psr18ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Factory\UploadedFileFactory;
use Slim\Psr7\Factory\UriFactory;
use Symfony\Component\HttpClient\Psr18Client;

require __DIR__ . '/../vendor/autoload.php';

session_start();

Dotenv::createImmutable(__DIR__ . '/../')->safeLoad();

$container = new Container();

$container->singleton(RequestFactoryInterface::class, RequestFactory::class);
$container->singleton(ServerRequestFactoryInterface::class, ServerRequestFactory::class);
$container->singleton(ResponseFactoryInterface::class, ResponseFactory::class);
$container->singleton(StreamFactoryInterface::class, StreamFactory::class);
$container->singleton(UploadedFileFactoryInterface::class, UploadedFileFactory::class);
$container->singleton(UriFactoryInterface::class, UriFactory::class);

$container->singleton(ClientInterface::class, function () {
    return new Psr18Client();
});

$container->singleton(HttpClientInterface::class, function () {
    $instance = new HttpClientManager($this->app->make(Psr18ClientInterface::class));

    $instance->setRequestFactory($this->app->make(RequestFactoryInterface::class));
    $instance->setResponseFactory($this->app->make(ResponseFactoryInterface::class));
    $instance->setServerRequestFactory($this->app->make(ServerRequestFactoryInterface::class));
    $instance->setStreamFactory($this->app->make(StreamFactoryInterface::class));
    $instance->setUploadedFileFactory($this->app->make(UploadedFileFactoryInterface::class));
    $instance->setUriFactory($this->app->make(UriFactoryInterface::class));

    return $instance;
});

$container->singleton(Issuer::class, function (Container $container) {
    return new Issuer(
        $container->make(HttpClientInterface::class)
    );
});

$container->singleton(Client::class, function (Container $container) {
    $openIdConnectConfig = require __DIR__ . '/../config.php';

    $provider = new ProviderMetadata(
        $openIdConnectConfig['google']['discovery'],
        $openIdConnectConfig['google']['jwks']
    );

    $client = new ClientMetadata([
        'client_id' => getenv('GOOGLE_CLIENT_ID'),
        'client_secret' => getenv('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => getenv('GOOGLE_REDIRECT_URI'),
    ]);

    return new Client(
        new OpenIdConnectConfig($provider, $client),
        $container->make(HttpClientInterface::class)
    );
});

$app = AppFactory::create(null, $container);

$app->get('/', function (RequestInterface $request, ResponseInterface $response) {
    $response->getBody()->write('Hi');

    return $response;
});

/**
 * Make an authentication request using the Authorization Code Flow.
 *
 * @see https://openid.net/specs/openid-connect-core-1_0.html#CodeFlowAuth
 */
$app->get('/code/rp-response_type-code', function () use ($container) {
    /** @var Issuer $issuer */
    $issuer = $container->make(Issuer::class);

    $provider = $issuer->discover('https://rp.certification.openid.net:8080/oidcphp-rp.code/rp-response_type-code');;

    $client = $container->make(Client::class);

    $state = Str::random();

    $_SESSION['state'] = $state;
    $_SESSION['provider'] = $provider->toArray();

    return $client->createAuthorizeRedirectResponse([
        'redirect_uri' => 'http://localhost:8080/callback',
        'response_type' => 'code',
        'response_mode' => 'query',
        'scope' => 'openid',
        'state' => $state,
    ]);
});

$app->get('/login-post', function () use ($container) {
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

    $token = $client->handleCallback($query, [
        'state' => $_SESSION['state'],
        'redirect_uri' => 'http://localhost:8080/callback',
    ]);

    dump($token->jsonSerialize());

    dump($token->idTokenClaims()->all());

    return $response;
});

return $app;
