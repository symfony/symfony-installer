Symfony Installer
=================

The **Symfony Installer** is the easiest and fastest way to create a new 
project based on the Symfony full-stack framework.

Installing the installer
------------------------

This step is only needed the first time you use the installer. We recommend you
to install it globally so you can use it anywhere on your system. To do so,
execute the following commands:

```bash
# install the Symfony installer
$ composer global require symfony/symfony-installer ~1.0@dev

# update the Symfony installer
$ composer global update symfony/symfony-installer
```

Once installed, this tool adds a new `symfony` binary that can be used to
easily access to all its features:

```bash
$ symfony
```

Using the installer
-------------------

**1. Start a new project with the latest Symfony version**

Execute the `new` command and provide the name of your project:

```bash
$ symfony new blog/
```

**2. Start a new project based on a specific Symfony version**

Execute the `new` command and provide the name of your project as the first argument followed by the needed version as the second argument:

```bash
$ symfony new blog/ 2.2.5
```
