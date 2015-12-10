<?php

/*
 * This file is part of the Symfony Installer package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Installer;

use GuzzleHttp\Client;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class Application extends ConsoleApplication
{
    /**
     * We tweak this function just to check the installer's version.
     *
     * {@inheritdoc}
     */
    public function doRun(InputInterface $input = null, OutputInterface $output = null)
    {
        $appVersion = trim(strtolower($this->getVersion()));

        try {
            $client = new Client();
            $response = $client->get('http://get.symfony.com/symfony.version');

            $distantVersion = trim(strtolower($response->getBody()));

            if ($distantVersion !== $appVersion && false === strpos($appVersion, 'dev')) {
                $output->writeln(sprintf(
                    '<comment>Warning: Your installer version is %s, while the latest one is %s. Run "%s selfupdate" to get the latest version.</comment>',
                    $appVersion, $distantVersion, $_SERVER['PHP_SELF']
                ));
            }

        } catch (\Exception $e) {
            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $output->writeln('<comment>Unable to retrieve installer\'s version</comment>');
            }
        }

        return parent::doRun($input, $output);
    }
}
