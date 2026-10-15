#!/usr/bin/env bash
cd "$(dirname $0)/.."
set -e

PHPDOC="./.phpdoc.phar"

# Install phpdoc
if ! [[ -f $PHPDOC ]]; then
    wget https://phpdoc.org/phpDocumentor.phar -O $PHPDOC

    if [[ $? != 0 ]]; then
        rm $PHPDOC
        exit 1
    fi

    chmod +x $PHPDOC
fi

# Build API classes
$PHPDOC run \
    --directory src/ \
    --target guides/api/ \
    --no-interaction \
    --no-ansi

echo

# Install mkdocs
if ! [[ -f venv/bin/activate ]]; then
    python3 -m venv venv/
fi

source venv/bin/activate
pip install --quiet \
    mkdocs-material=="8.*"

# Build guides
mkdocs build
