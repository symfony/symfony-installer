Symfony Installer
=================

**This is the official installer to start new projects based on the Symfony 
full-stack framework.**

Installing the installer
------------------------

To install `Symfony Installer` its recommended to first install [Composer](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-osx). If you do not wish to use composer to manage the `Symfony Installer` you can check out the [alternative installation method](alternative-installation.md).

If you have composer installed you can install `Symfony Installer`:


```bash
$ composer require symfony/symfony-installer --dev
```

To allow you to call the command you eather have to use the command: 

```bash
./vendor/bin/symfony
```

Or add the `./vendor/bin` to your path. On Linux and Mac OSX you can do this by the following command:

```bash
export PATH=$PATH./vendor/bin
```

It's recommended to add this line to you `.bashrc` or `.zshrc` file so you don't have to run it every time you open a new terminal.


Using the installer
-------------------

**1. Start a new project with the latest stable Symfony version**

Execute the `new` command and provide the name of your project as the only
argument:

```bash
# Linux, Mac OS X
$ symfony new my_project

# Windows
c:\> php symfony new my_project
```

**2. Start a new project with the latest Symfony LTS (Long Term Support) version**

Execute the `new` command and provide the name of your project as the first
argument and `lts` as the second argument. The installer will automatically
select the most recent LTS (*Long Term Support*) version available:

```bash
# Linux, Mac OS X
$ symfony new my_project lts

# Windows
c:\> php symfony new my_project lts
```

**3. Start a new project based on a specific Symfony branch**

Execute the `new` command and provide the name of your project as the first
argument and the branch number as the second argument. The installer will
automatically select the most recent version available for the given branch:

```bash
# Linux, Mac OS X
$ symfony new my_project 2.3

# Windows
c:\> php symfony new my_project 2.3
```

**4. Start a new project based on a specific Symfony version**

Execute the `new` command and provide the name of your project as the first
argument and the exact Symfony version as the second argument:

```bash
# Linux, Mac OS X
$ symfony new my_project 2.5.6

# Windows
c:\> php symfony new my_project 2.5.6
```

Updating the installer
----------------------

To update simply re-run:

```bash
$ composer require symfony/symfony-installer --dev
```
