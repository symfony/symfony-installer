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

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Application as ConsoleApplication;

/**
 * This is main Symfony Installer console application class.
 *
 * @author Jerzy Zawadzki <zawadzki.jerzy@gmail.com>
 */
class Application extends ConsoleApplication
{
    const VERSIONS_URL = 'https://get.symfony.com/symfony.version';

    /**
     * {@inheritdoc}
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        return parent::doRun($input, $output);
    }
}
