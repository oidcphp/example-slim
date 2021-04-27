<?php

namespace App\Commands;

use MilesChou\Psr\Http\Client\HttpClientManager;
use MilesChou\Psr\Http\Message\ResponseFactory;
use MilesChou\Psr\Http\Message\StreamFactory;
use OpenIDConnect\Issuer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;

class Discover extends Command
{
    private const TEMPLATE = <<<EOF
<?php

return %s;

EOF;

    protected function configure()
    {
        parent::configure();

        $this->setDescription('Discover the OIDC provider');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        file_put_contents(getcwd() . '/config.php', sprintf(self::TEMPLATE, var_export([
            'google' => $this->discoverGoogle()->toArray(),
        ], true)));

        return 0;
    }

    private function discoverGoogle()
    {
        $httpClient = new HttpClientManager(new Psr18Client(
            HttpClient::create(),
            new ResponseFactory(),
            new StreamFactory()
        ));

        return (new Issuer($httpClient))->discover('https://accounts.google.com/.well-known/openid-configuration');
    }
}
