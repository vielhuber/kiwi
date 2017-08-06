# 🥝 kiwi 🥝

**kiwi provides a git-like experience for relational databases.**

![kiwi in action](https://raw.githubusercontent.com/vielhuber/kiwi/master/kiwi.gif)

This is currently a proof-of-concept.

## Features

* No sql triggers
* No binary / ddl logs
* Support for mysql & mariadb
* Works with any shared hosting provider that runs linux
* Detects [data and schema changes](https://github.com/vielhuber/magicdiff), at the same time
* Fast
* Command line tool usage
* Requires only ssh access to remote repository
* [Search/replace layer](https://github.com/vielhuber/magicreplace) for environment specific values (serialize safe)
* Works together with WordPress, Shopware or any other raw sql database

## Similiar tools

* [mergebot](https://mergebot.com)

## Planned

* Support for postgresql
* Advanced conflict solver
* Support for syncing views, trigger, functions and transactions
* Test suite
* Branching and other git-like features

## Disclaimer

This does not prevent you from taking backups. Use this script at your own risk.

## Dependencies

* [php7](http://php.net/)
* [diff](https://linux.die.net/man/1/diff)
* [patch](https://linux.die.net/man/1/patch)
* [scp](https://linux.die.net/man/1/scp)

## Installation

Install/update globally:
```
wget https://raw.githubusercontent.com/vielhuber/kiwi/master/kiwi.phar
chmod +x kiwi.phar
sudo mv kiwi.phar /usr/local/bin/kiwi
```

## Usage

First setup kiwi on client:

`kiwi init`

Change settings for local/remote database:

`nano .kiwi/config.json`

Get current status:

`kiwi status`

Push changes to remote repo:

`kiwi push`

Pull state of remote repo:

`kiwi pull`