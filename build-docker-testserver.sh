#!/bin/bash

docker build --rm -f "Dockerfile.testserver" -t tiqr-testserver:latest "."
