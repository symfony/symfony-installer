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

/**
 * This command creates a full-featured Symfony demo application.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class DemoCommand extends DownloadCommand
{
    protected $projectName;
    protected $projectDir;
    protected $downloadedFilePath;
    protected $requirementsErrors = array();

    protected function configure()
    {
        $this
            ->setName('demo')
            ->setDescription('Creates a demo Symfony project.')
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $this->projectDir = getcwd();

        $i = 1;
        $projectName = 'symfony_demo';
        while (file_exists($this->projectDir.DIRECTORY_SEPARATOR.$projectName)) {
            $projectName = 'symfony_demo_'.(++$i);
        }

        $this->projectName = $projectName;
        $this->projectDir = $this->projectDir.DIRECTORY_SEPARATOR.$projectName;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this
                ->download()
                ->extract()
                ->cleanUp()
                ->createGitIgnore()
                ->checkSymfonyRequirements()
                ->displayInstallationResult()
            ;
        } catch (\Exception $e) {
            $this->cleanUp();
            throw $e;
        }
    }

    /**
     * Removes all the temporary files and directories created to
     * download the demo application.
     *
     * @return NewCommand
     */
    private function cleanUp()
    {
        $this->fs->remove(dirname($this->downloadedFilePath));

        return $this;
    }

    /**
     * It displays the message with the result of installing the Symfony Demo
     * application and provides some pointers to the user.
     *
     * @return DemoCommand
     */
    private function displayInstallationResult()
    {
        if (empty($this->requirementsErrors)) {
            $this->output->writeln(sprintf(
                " <info>%s</info>  Symfony Demo Application was <info>successfully installed</info>. Now you can:\n",
                defined('PHP_WINDOWS_VERSION_BUILD') ? 'OK' : '✔'
            ));
        } else {
            $this->output->writeln(sprintf(
                " <comment>%s</comment>  Symfony Demo Application was <info>successfully installed</info> but your system doesn't meet the\n".
                "     technical requirements to run Symfony applications! Fix the following issues before executing it:\n",
                defined('PHP_WINDOWS_VERSION_BUILD') ? 'FAILED' : '✕'
            ));

            foreach ($this->requirementsErrors as $helpText) {
                $this->output->writeln(' * '.$helpText);
            }

            $this->output->writeln(sprintf(
                " After fixing these issues, re-check Symfony requirements executing this command:\n\n".
                "   <comment>php %s/app/check.php</comment>\n\n".
                " Then, you can:\n",
                $this->projectName
            ));
        }

        $this->output->writeln(sprintf(
            "    1. Change your current directory to <comment>%s</comment>\n\n".
            "    2. Execute the <comment>php app/console server:run</comment> command to run the demo application.\n\n".
            "    3. Browse to the <comment>http://localhost:8000</comment> URL to see the demo application in action.\n\n",
            $this->projectDir
        ));

        return $this;
    }

    protected function getDownloadedApplicationType()
    {
        return 'Symfony Demo Application';
    }

    protected function getRemoteFileUrl()
    {
        return 'http://symfony.com/download?v=Symfony_Demo';
    }
}
