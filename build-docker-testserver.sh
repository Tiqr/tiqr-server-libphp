#!/bin/bash

docker pull php:8.2-apache

docker build --rm -f "Dockerfile.testserver" -t tiqr-testserver:latest "."
