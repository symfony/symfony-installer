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

use GuzzleHttp;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This command retrieves informations about Symfony available versions
 *
 * @author Alexandre "Pierstoval" Ancelet <pierstoval@gmail.com>
 */
class VersionCommand extends Command
{

    private $versionsUrl = 'http://symfony.com/versions.json';

    private $roadmapUrl = 'http://symfony.com/roadmap.json';

    protected function configure()
    {
        $this
            ->setName('versions')
            ->setDescription('View informations about Symfony versions.')
            ->addArgument('version', InputArgument::OPTIONAL, 'The Symfony version to check.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $json = @file_get_contents($this->versionsUrl);

        if (false === $json) {
            $output->writeln('<error>The versions could not be retrieved from the server. To solve this issue, you can check your internet connection.</error>');
            return 1;
        }

        $versions = GuzzleHttp\json_decode($json, true);

        $versionArgument = $input->getArgument('version');

        if (!$versionArgument) {
            $table = $this->getHelperSet()->get('table');
            $table->setRows(array(
                array('Long-term support', $versions['lts']),
                array('Latest', $versions['latest']),
                array('Development', $versions['dev']),
            ));
            $table->render($output);
        } else {
            $versionArgument = trim($versionArgument, '.');

            $output->writeln('Checking informations about version <info>' . $versionArgument . '</info>');
            $output->writeln('');

            $minorVersion = preg_replace('~^([0-9])\.([0-9]+)(\.[0-9]+)?$~', '$1.$2', $versionArgument);

            // First, check if the version is referenced in the Symfony Roadmap
            $roadmapJson = @file_get_contents($this->roadmapUrl . '?version=' . $minorVersion);
            $versionInRoadmap = null;
            if (false !== $roadmapJson) {
                // Get informations about the version in the roadmap
                $roadmap = GuzzleHttp\json_decode($roadmapJson, true);
                if (isset($roadmap['error_message'])) {
                    // Show message from the Symfony Roadmap API
                    $output->writeln('<error>' . $roadmap['error_message'] . '</error>');
                    return 2;
                } elseif (isset($roadmap['is_latest']) && isset($roadmap['is_lts'])) {
                    $versionInRoadmap = $roadmap;
                }
            }

            // Then, we show informations about the minor version
            if (preg_match('~^[0-9]\.[0-9]+~', $versionArgument)) {
                // Check x.y version

                if (isset($versions[$minorVersion])) {
                    // Version is referenced in the symfony.json remote file
                    $output->writeln('Released in: <info>' . $versionInRoadmap['release_date'] . '</info>.');
                    $output->writeln('The latest version for the ' . $minorVersion . ' branch is <info>' . $versions[$minorVersion] . '</info>');
                    if ($versionInRoadmap) {
                        $output->writeln('Is in the latest branch: <info>' . ($versionInRoadmap['is_latest'] ? 'true' : 'false') . '</info>.');
                        $output->writeln('Is long-term supported: <info>' . ($versionInRoadmap['is_lts'] ? 'true' : 'false') . '</info>.');
                    }
                } else {
                    // Version is NOT referenced in the symfony.json remote file
                    if ($versionInRoadmap) {
                        if ($versionInRoadmap['is_eomed']) {
                            $output->writeln('Released in: <info>' . $versionInRoadmap['release_date'] . '</info>.');
                        } else {
                            $output->writeln('This version is not yet supported, though its release date is estimated in ' . $versionInRoadmap['release_date'] . '.');
                        }
                        $output->writeln('This version is not referenced in the active ones, you should check for <info>lts</info>, <info>latest</info> or <info>dev</info> version.');
                    } else {
                        $output->writeln('This version is not supported or does not exist.');
                    }
                }

            }

            // And finally, if user specified the "patch" version, we show infos about it
            if (preg_match('~^[0-9]\.[0-9]+\.[0-9]+$~', $versionArgument)) {
                // Check x.y.z version

                $installable = null;
                if (in_array($versionArgument, $versions['non_installable'])) {
                    $installable = '<error>false</error>';
                } elseif (in_array($versionArgument, $versions['installable'])) {
                    $installable = '<info>true</info>';
                }

                $output->writeln('Is considered as installable : ' . $installable);

            }
        }


    }

}