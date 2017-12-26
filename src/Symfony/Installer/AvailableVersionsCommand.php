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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This command provides information about all installable versions of Symfony.
 *
 * @author Bertrand Zuchuat <bertrand.zuchuat@gmail.com>
 */
class AvailableVersionsCommand extends DownloadCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('available-versions')
            ->setDescription('Symfony Available Versions.')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        
        // Get the full list of Symfony versions
        $client = $this->getGuzzleClient();
        $symfonyVersions = $client->get($this->getRemoteFileUrl())->json();
        if (empty($symfonyVersions)) {
            throw new \RuntimeException(
                "There was a problem while downloading the list of Symfony versions from\n".
                "symfony.com. Check that you are online and the following URL is accessible:\n\n".
                $this->getRemoteFileUrl()
            );
        }
        
        $installable = array();
        array_walk($symfonyVersions['installable'], function($version) use (&$installable) {
            list($sfversion, $sfmajor, $sfminor) = explode('.', $version);
            $branch = $sfversion.'.'.$sfmajor;
            if (!array_key_exists($branch, $installable)) {
                $installable[$branch] = array();
            }
            array_push($installable[$branch], $version);
        });
        $keys = array_keys($installable);
        $lastKey = end($keys);

        $output->writeln('<comment>Available tags version</comment>');
        $table = new Table($output);
        $table
            ->setHeaders(array('Type', 'Version'))
            ->setRows(array(
                array($this->getColumnStyleGreen('lts'), $symfonyVersions['lts']),
                array($this->getColumnStyleGreen('latest'), $symfonyVersions['latest']),
                array($this->getColumnStyleGreen('dev'), $symfonyVersions['dev']),
            ));
        $table->render();

        $output->writeln('<comment>Available versions by branch</comment>');
        $table = new Table($output);
        $table
            ->setHeaders(array('Branches', 'Available versions'));
        foreach ($installable as $branch => $versions) {
            $versions[count($versions) - 1] = $this->getColumnStyleGreen(
                $versions[count($versions) - 1]
            );
            $rows = array_map(function($values) {
                return implode(', ', $values);
            }, array_chunk($versions, 8));
            $table->addRow(array(
                new TableCell($this->getColumnStyleGreen($branch)),
                new TableCell(implode(",\n", $rows))
            ));
            if ($lastKey != $branch) {
                $table->addRow(new TableSeparator());
            }
        }
        $table->render();
    }

    /**
     * {@inheritdoc}
     */
    protected function getDownloadedApplicationType()
    {
    }

    /**
     * {@inheritdoc}
     */
    protected function getRemoteFileUrl()
    {
        return 'http://symfony.com/versions.json';
    }

    private function getColumnStyleGreen($value)
    {
        return sprintf('<info>%s</info>', $value);
    }
}
