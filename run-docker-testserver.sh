#!/bin/bash

docker run -it --rm -v $(pwd)/TestServer/config/:/var/www/TestServer/config -v $(pwd)/TestServer/storage:/var/www/TestServer/storage -p 8000:80/tcp tiqr-testserver:latest