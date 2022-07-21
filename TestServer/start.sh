#!/bin/bash
# Start script for apache testserver docker container

echo "CONFIG_DIR=${CONFIG_DIR}"
echo "STORAGE_DIR=${STORAGE_DIR}"

# Create /config directory from .dist version if it does not exists.
if [ ! -d /config ]; then
    cp -R /var/www/TestServer/config.dist /config    
fi
# Allow read access for all so apache-php can read this directory
chmod -R a+rx /config

if [ ! -d /storage ]; then
    mkdir /storage    
fi
# Allow read and write access to all so apache can write to this directory
chmod -R a+rwx /storage

# Start apache server
/usr/local/bin/apache2-foreground