#!/bin/bash
# Start script for apache testserver in the docker container

# Create /config directory from .dist version if it does not exists.
if [ ! -d /var/www/TestServer/config ]; then
    cp -R /var/www/TestServer/config.dist /var/www/TestServer/config && echo "WARNING: /config directory does not exist. Created /var/www/TestServer/config directory from /var/www/TestServer/config.dist"
fi
# Allow read access for all so apache-php can read this directory
chmod -R a+rx /var/www/TestServer/config

if [ ! -d /var/www/TestServer/storage ]; then
    mkdir /var/www/TestServer/storage && "WARNING: /var/www/TestServer/storage directory does not exist. Created /var/www/TestServer/storage directory"
fi
# Allow read and write access to all so apache can write to this directory
chmod -R a+rwx /var/www/TestServer/storage

# Start apache server
/usr/local/bin/apache2-foreground