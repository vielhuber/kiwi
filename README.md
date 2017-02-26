# 🥝 kiwi 🥝

**kiwi provides a git-like experience for databases.**

* no sql triggers
* no binary / ddl logs
* works with any shared hosting provider
* data and schema changes, at the same time
* blazingly fast
* command line tool usage
* requires only ssh access to remote repository
* open source and free
* support for both mysql and postgresql
* search/replace mask function for production/staging/dev specific strings
* test suite

## installation

first setup kiwi on client:
`kiwi init`

then rollback local db to empty state:
`kiwi rollback`

pull state of remote repo:
`kiwi pull all`


## usage

get current status:
`kiwi status`

pull if needed:
`kiwi pull`

push to remote:
`kiwi push`
