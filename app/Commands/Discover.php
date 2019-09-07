<?php

namespace App\Commands;

use OpenIDConnect\Issuer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
        return Issuer::create('https://accounts.google.com/')->discover();
    }
}
