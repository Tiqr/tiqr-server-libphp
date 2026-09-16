#!/usr/bin/env bash

cd $(dirname $0)/../../

./vendor/bin/rector --config=ci/qa/rector.php "$@"
