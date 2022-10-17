#!/bin/bash

SCRIPT_DIR="$(dirname "$(which "$0")")"
pushd "${SCRIPT_DIR}" > /dev/null || exit
SCRIPT_DIR=$(pwd)
popd > /dev/null || exit
CONFIG_DIR="${SCRIPT_DIR}/TestServer/config/"
echo CONFIG_DIR="${CONFIG_DIR}"
STORAGE_DIR="${SCRIPT_DIR}/TestServer/storage/"
echo STORAGE_DIR="${STORAGE_DIR}"
docker run -it --rm -v "${CONFIG_DIR}:/var/www/TestServer/config" -v "${STORAGE_DIR}:/var/www/TestServer/storage" -p 8000:80/tcp tiqr-testserver:latest
