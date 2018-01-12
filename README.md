Symfony Installer
=================

This is the official installer to start new projects based on the Symfony
full-stack framework. The installer is only compatible with Symfony 2 and 3.

Creating Symfony 4 projects
---------------------------

**This installer is not compatible with Symfony 4** and newer versions. Instead,
use [Composer](https://getcomposer.org/) and create your Symfony 4 project as follows:

```bash
$ composer create-project symfony/skeleton my_project_name
```

See the [Symfony Installation article](https://symfony.com/doc/current/setup.html)
on the official Symfony Documentation for more details.

Installing the installer
------------------------

This step is only needed the first time you use the installer:

### Linux and Mac OS X

```bash
$ sudo curl -LsS https://symfony.com/installer -o /usr/local/bin/symfony
$ sudo chmod a+x /usr/local/bin/symfony
```

### Windows

```bash
c:\> php -r "file_put_contents('symfony', file_get_contents('https://symfony.com/installer'));"
```

Move the downloaded `symfony` file to your projects directory and execute
it as follows:

```bash
c:\> php symfony
```

If you prefer to create a global `symfony` command, execute the following:

```bash
c:\> (echo @ECHO OFF & echo php "%~dp0symfony" %*) > symfony.bat
```

Then, move both files (`symfony` and `symfony.bat`) to any location included
in your execution path. Now you can run the `symfony` command anywhere on your
system.

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
$ symfony new my_project 2.8

# Windows
c:\> php symfony new my_project 2.8
```

**4. Start a new project based on a specific Symfony version**

Execute the `new` command and provide the name of your project as the first
argument and the exact Symfony version as the second argument:

```bash
# Linux, Mac OS X
$ symfony new my_project 2.8.1

# Windows
c:\> php symfony new my_project 2.8.1
```

**5. Install the Symfony demo application**

The Symfony Demo is a reference application developed using the official Symfony
Best Practices:

```bash
# Linux, Mac OS X
$ symfony demo

# Windows
c:\> php symfony demo
```

Updating the installer
----------------------

New versions of the Symfony Installer are released regularly. To update your
installer version, execute the following command:

```bash
# Linux, Mac OS X
$ symfony self-update

# Windows
c:\> php symfony self-update
```

> **NOTE**
>
> If your system requires the use of a proxy server to download contents, the
> installer tries to guess the best proxy settings from the `HTTP_PROXY` and
> `http_proxy` environment variables. Make sure any of them is set before
> executing the Symfony Installer.

Troubleshooting
---------------

### SSL and certificates issues on Windows systems

If you experience any error related with SSL or security certificates when using
the Symfony Installer on Windows systems:

1) Check that the OpenSSL extension is enabled in your `php.ini` configuration:

```ini
; make sure that the following line is uncommented
extension=php_openssl.dll
```

2) Check that the path to the file that contains the security certificates
exists and is defined in `php.ini`:

```ini
openssl.cafile=C:/path/to/cacert.pem
```

If you can't locate the `cacert.pem` file anywhere on your system, you can
safely download it from the official website of the cURL project:
http://curl.haxx.se/ca/cacert.pem
