#!/usr/bin/env bash
set -e
cd "$(dirname $0)"

MYSQL_DATABASE='kbpdb'
MYSQL_USER='kbpdb'

echo << SQL | mysql
CREATE DATABASE IF NOT EXISTS \`$MYSQL_DATABASE\`;
CREATE USER IF NOT EXISTS '$MYSQL_USER'@'%' IDENTIFIED BY 'password';
GRANT ALL ON \`$MYSQL_DATABASE\`.* TO '$MYSQL_USER'@'%';
SQL
