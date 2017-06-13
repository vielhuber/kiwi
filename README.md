# 🥝 kiwi 🥝

**kiwi provides a git-like experience for relational databases.**

![kiwi in action](https://raw.githubusercontent.com/vielhuber/kiwi/master/kiwi.gif)

## Features

* no sql triggers
* no binary / ddl logs
* zero dependencies
* support for mysql & mariadb
* works with any shared hosting provider that runs linux
* detects [data and schema changes](https://github.com/vielhuber/magicdiff), at the same time
* blazingly fast
* command line tool usage
* requires only ssh access to remote repository
* open source and free
* [search/replace layer](https://github.com/vielhuber/magicreplace) for environment specific values (serialize safe!)
* works together with WordPress, Shopware or any other raw sql database

## Planned

* support for postgresql
* advanced conflict solver
* support for syncing views, trigger, functions and transactions
* test suite

## Disclaimer

This does not prevent you from taking backups. Use this script at your own risk.

## Client requirements

* php7
* diff
* patch
* scp

## Installation

Install/update globally:
```
wget https://raw.githubusercontent.com/vielhuber/kiwi/master/kiwi.phar
chmod +x kiwi.phar
sudo mv kiwi.phar /usr/local/bin/kiwi
```

## Usage

first setup kiwi on client:

`kiwi init`

change settings for local/remote database:

`nano .kiwi/config.json`

get current status:

`kiwi status`

push changes to remote repo:

`kiwi push`

pull state of remote repo:

`kiwi pull`