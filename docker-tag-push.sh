#!/bin/bash

set -e

docker tag tiqr-testserver pmeulen/tiqr-testserver:latest
docker push pmeulen/tiqr-testserver:latest
