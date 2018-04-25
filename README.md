# Symfony Lock Flysystem Adapter

This adapter allows you to use any flysystem filesystem instance as store for the
[symfony/lock](https://github.com/symfony/lock) component.

## Installation

```bash
composer require xtain/symfony-lock-flysystem
```

## Usage

```php
use XTAIN\Flysystem\Lock\FlysystemStore;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;
use Symfony\Component\Lock\Factory;

$filesystem = new Filesystem(new Local(sys_get_temp_dir()));
$factory = new Factory(new FlysystemStore($filesystem));

$lock = $factory->createLock('test', 5);
$lock->acquire();

sleep(4);
$lock->refresh();

sleep(4);
$lock->refresh();
$lock->isExpired() === false;

```
