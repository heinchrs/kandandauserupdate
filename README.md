# kandandauserupdate

## Description

This project builds a plugin called **Kandanda Userupdate** for the content management system [Joomla](https://www.joomla.org/).

The module requires in Joomla an installation of the [Kandanda-Component](https://www.kandanda.net).

Using this plugin, it is possible to send emails if Kandanda memberdata is changed or newly inserted.

## Folder structure
- .release -> contains the installation archive of joomla plugin and the corresponding update XML
- .vscode -> contains the VSCode task file for creating joomla archive
- language -> contains the language specific translations

## Joomla packaging
For packaging the Joomla module a powershell script _CreateArchive.ps1 exists. This script packs all needed files into a zip archive.

## Joomla coding style
In order to fullfil [Joomla coding standard](https://github.com/joomla/coding-standards) PHP Codesniffer has been used for formatting php source files.
For installation [Composer](https://getcomposer.org/download/) is used.
After installation of Composer create file composer.json in user directory and add the following lines:
```json
{
    "require": {
        "squizlabs/php_codesniffer": "~2.8"
    },

    "require-dev": {
        "joomla/coding-standards": "~2.0@alpha"
    }
}
```
On Command line execute
```cmd
composer update
```
Installation is done in \<Userdir\>\vendor
and also in \<Userdir\>\AppData\Roaming\Composer\vendor
but Joomla coding style is only installed at \<Userdir\>\vendor\joomla

**Hint**
Since php codesniffer 2.8 is not optimized for php 7.4, several deprecated warnings are emitted, which leads to a not working sniffer output. Therefore adapt \<Userdir\>\AppData\Roaming\Composer\vendor\squizlabs\php_codesniffer\CodeSniffer.php and add
```php
$errorlevel = error_reporting();
$errorlevel = error_reporting($errorlevel & ~E_DEPRECATED);
```

## Visual Studio Code
Since Visual Studio Code is used as IDE install there extension phpcs.
In settings adapt the following options
- executable path for phpcs
\<Userdir\>\AppData\Roaming\Composer\vendor\bin\phpcs
- phpcs: Standard -> settings.json
"phpcs.standard": "\<Userdir\>\\vendor\\joomla\\coding-standards\\Joomla"

