#!/bin/bash

docker pull php:8.5-apache

docker build --rm -f "Dockerfile.testserver" -t tiqr-testserver:latest "."
