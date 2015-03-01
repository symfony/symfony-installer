Symfony Installer
=================

**This is the official installer to start new projects based on the Symfony 
full-stack framework.**

Installing the installer
------------------------

This step is only needed the first time you use the installer:

### Linux and Mac OS X

```bash
$ curl -LsS http://symfony.com/installer > symfony.phar
$ sudo mv symfony.phar /usr/local/bin/symfony
$ chmod a+x /usr/local/bin/symfony
```

### Windows

```bash
c:\> php -r "readfile('http://symfony.com/installer');" > symfony.phar
```

Move the downloaded `symfony.phar` file to your projects directory and execute 
it as follows:

```bash
c:\> php symfony.phar
```

Using the installer
-------------------

**1. Start a new project with the latest Symfony version**

Execute the `new` command and provide the name of your project:

```bash
# Linux, Mac OS X
$ symfony new my_project

# Windows
$ php symfony.phar new my_project
```

**2. Start a new project based on a specific Symfony branch**

Execute the `new` command and provide the name of your project as the first
argument and the branch number as the second argument. The installer will
automatically select the most recent version available for the given branch:

```bash
# Linux, Mac OS X
$ symfony new my_project 2.3

# Windows
$ php symfony.phar new my_project 2.3
```

**3. Start a new project based on a specific Symfony version**

Execute the `new` command and provide the name of your project as the first
argument and the exact Symfony version as the second argument:

```bash
# Linux, Mac OS X
$ symfony new my_project 2.5.6

# Windows
$ php symfony.phar new my_project 2.5.6
```

**4. Start a new project with custom installer
Execute the `new` command and provide a file where you put a class implements the interface `Symfony\Install\InstallerInterface` or extends of `Symfony\Install\Installer`.
You must see `https://github.com/symfony/symfony-installer`. The file must return the object. Example.

```php
namespace CustomInstaller;

use Symfony\Install\Installer;

class CustomInstaller extends Installer
{
    protected $skeleton = 'http://custom/installer/Symfony_Standard_Vendors_';
}

return new CustomInstaller();
```

Updating the installer
----------------------

New versions of the Symfony Installer are released regularly. To update your
installer version, execute the following command:

```bash
# Linux, Mac OS X
$ symfony self-update

# Windows
$ php symfony.phar self-update
```
