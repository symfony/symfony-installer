<?php

namespace Symfony\Installer\Manager;

class ComposerManager
{
    /**
     * Generates a good Composer project name based on the application name
     * and on the user name.
     *
     * @param $projectName
     *
     * @return string The generated Composer package name
     */
    public function generatePackageName($projectName)
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