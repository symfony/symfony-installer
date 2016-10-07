<?php

namespace Symfony\Installer\Manager;

use Symfony\Component\Filesystem\Filesystem;

class ComposerManager
{
    private $projectDir;
    private $fs;

    public function __construct($projectDir)
    {
        $this->projectDir = $projectDir;
        $this->fs = new Filesystem();
    }

    public function initializeProjectConfig()
    {
        $composerConfig = $this->getProjectConfig();

        if (isset($composerConfig['config']['platform']['php'])) {
            unset($composerConfig['config']['platform']['php']);

            if (empty($composerConfig['config']['platform'])) {
                unset($composerConfig['config']['platform']);
            }

            if (empty($composerConfig['config'])) {
                unset($composerConfig['config']);
            }
        }

        $this->saveProjectConfig($composerConfig);
    }

    public function updateProjectConfig(array $newConfig)
    {
        $oldConfig = $this->getProjectConfig();
        $projectConfig = array_replace_recursive($oldConfig, $newConfig);

        // remove null values from project's config
        $projectConfig = array_filter($projectConfig, function($value) { return !is_null($value); });

        $this->saveProjectConfig($projectConfig);
    }

    public function getPackageVersion($packageName)
    {
        $composerLockFileContents = json_decode(file_get_contents($this->projectDir.'/composer.lock'), true);

        foreach ($composerLockFileContents['packages'] as $packageConfig) {
            if ($packageName === $packageConfig['name']) {
                return $packageConfig['version'];
            }
        }
    }

    /**
     * Generates a good Composer project name based on the application name
     * and on the user name.
     *
     * @param $projectName
     *
     * @return string The generated Composer package name
     */
    public function createPackageName($projectName)
    {
        if (!empty($_SERVER['USERNAME'])) {
            $packageName = $_SERVER['USERNAME'].'/'.$projectName;
        } elseif (true === extension_loaded('posix') && $user = posix_getpwuid(posix_getuid())) {
            $packageName = $user['name'].'/'.$projectName;
        } elseif (get_current_user()) {
            $packageName = get_current_user().'/'.$projectName;
        } else {
            // package names must be in the format foo/bar
            $packageName = $projectName.'/'.$projectName;
        }

        return $this->fixPackageName($packageName);
    }

    /**
     * It returns the project's Composer config as a PHP array.
     *
     * @return array
     */
    private function getProjectConfig()
    {
        $composerJsonPath = $this->projectDir.'/composer.json';
        if (!is_writable($composerJsonPath)) {
            return [];
        }

        return json_decode(file_get_contents($composerJsonPath), true);
    }

    /**
     * It saves the given PHP array as the project's Composer config. In addition
     * to JSON-serializing the contents, it synchronizes the composer.lock file to
     * avoid out-of-sync Composer errors.
     *
     * @param array $config
     */
    private function saveProjectConfig(array $config)
    {
        $composerJsonPath = $this->projectDir.'/composer.json';
        $this->fs->dumpFile($composerJsonPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        $this->syncComposerLockFile();
    }

    /**
     * Updates the hash values stored in composer.lock to avoid out-of-sync
     * problems when the composer.json file contents are changed.
     */
    private function syncComposerLockFile()
    {
        $composerJsonFileContents = file_get_contents($this->projectDir.'/composer.json');
        $composerLockFileContents = json_decode(file_get_contents($this->projectDir.'/composer.lock'), true);

        if (array_key_exists('hash', $composerLockFileContents)) {
            $composerLockFileContents['hash'] = md5($composerJsonFileContents);
        }

        if (array_key_exists('content-hash', $composerLockFileContents)) {
            $composerLockFileContents['content-hash'] = $this->getComposerContentHash($composerJsonFileContents);
        }

        $this->fs->dumpFile($this->projectDir.'/composer.lock', json_encode($composerLockFileContents, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }

    /**
     * Returns the md5 hash of the sorted content of the composer file.
     *
     * @see https://github.com/composer/composer/blob/master/src/Composer/Package/Locker.php (getContentHash() method)
     *
     * @param string $composerJsonFileContents The contents of the composer.json file.
     *
     * @return string The hash of the composer file content.
     */
    private function getComposerContentHash($composerJsonFileContents)
    {
        $composerConfig = json_decode($composerJsonFileContents, true);

        $relevantKeys = array(
            'name',
            'version',
            'require',
            'require-dev',
            'conflict',
            'replace',
            'provide',
            'minimum-stability',
            'prefer-stable',
            'repositories',
            'extra',
        );

        $relevantComposerConfig = array();

        foreach (array_intersect($relevantKeys, array_keys($composerConfig)) as $key) {
            $relevantComposerConfig[$key] = $composerConfig[$key];
        }

        if (isset($composerConfig['config']['platform'])) {
            $relevantComposerConfig['config']['platform'] = $composerConfig['config']['platform'];
        }

        ksort($relevantComposerConfig);

        return md5(json_encode($relevantComposerConfig));
    }

    /**
     * Transforms a project name into a valid Composer package name.
     *
     * @param string $name The project name to transform
     *
     * @return string The valid Composer package name
     */
    private function fixPackageName($name)
    {
        $name = str_replace(
            ['à', 'á', 'â', 'ä', 'æ', 'ã', 'å', 'ā', 'é', 'è', 'ê', 'ë', 'ę', 'ė', 'ē', 'ī', 'į', 'í', 'ì', 'ï', 'î', 'ō', 'ø', 'œ', 'õ', 'ó', 'ò', 'ö', 'ô', 'ū', 'ú', 'ù', 'ü', 'û', 'ç', 'ć', 'č', 'ł', 'ñ', 'ń', 'ß', 'ś', 'š', 'ŵ', 'ŷ', 'ÿ', 'ź', 'ž', 'ż'],
            ['a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'u', 'c', 'c', 'c', 'l', 'n', 'n', 's', 's', 's', 'w', 'y', 'y', 'z', 'z', 'z'],
            $name
        );
        $name = preg_replace('#[^A-Za-z0-9_./-]+#', '', $name);

        return strtolower($name);
    }
}
