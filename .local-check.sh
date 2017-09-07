#!/bin/bash

set -u

SELF_DIR=$(cd $(dirname $0);pwd)
cd "$SELF_DIR"

VENDOR_DIR="./vendor"

if [ ! -d $VENDOR_DIR ] && [ -d ../../../vendor ]; then
    VENDOR_DIR="../../../vendor"
fi

if [ ! -d $VENDOR_DIR ]; then
    echo "Not found VENDOR_DIR"
fi

${VENDOR_DIR}/bin/phpunit --stderr

cd ${VENDOR_DIR}/../
vendor/bin/phpcs -p --extensions=php --standard=${VENDOR_DIR}/cakephp/cakephp-codesniffer/CakePHP "${SELF_DIR}/src" "${SELF_DIR}/tests"