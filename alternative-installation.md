Alternative installation method
------------------------

This step is only needed the first time you use the installer:

### Linux and Mac OS X

```bash
$ sudo curl -LsS http://symfony.com/installer -o /usr/local/bin/symfony
$ sudo chmod a+x /usr/local/bin/symfony
```

### Windows

```bash
c:\> php -r "file_put_contents('symfony', file_get_contents('http://symfony.com/installer'));"
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
