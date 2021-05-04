#!/bin/bash

rm -rf .Build
rm composer.lock
composer config --unset platform.php
composer config --unset platform
