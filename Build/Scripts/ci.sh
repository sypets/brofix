#!/bin/bash

# 7.4 and composer latest

# abort on error
set -e

echo "composer install"
Build/Scripts/runTests.sh -p 7.4 -s composerInstallMax

echo "cgl"
Build/Scripts/runTests.sh -p 7.4 -s cgl -n

echo "composer validate"
Build/Scripts/runTests.sh -p 7.4 -s composerValidate

echo "lint"
Build/Scripts/runTests.sh -p 7.4 -s lint

echo "phpstan"
Build/Scripts/runTests.sh -p 7.4 -s phpstan -e "-c ../Build/phpstan.neon"

echo "Unit tests"
Build/Scripts/runTests.sh -p 7.4 -s unit

echo "functional tests"
Build/Scripts/runTests.sh -p 7.4 -d mariadb -s functional

echo "done"
