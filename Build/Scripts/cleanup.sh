#!/bin/bash

rm -rf .Build
rm composer.lock
composer config --unset platform.php
composer config --unset platform
composer require typo3/cms-backend:"^10.4.14 || ^11.5.3"
composer require typo3/cms-core:"^10.4.14 || ^11.5.3"
composer require typo3/cms-fluid:"^10.4.14 || ^11.5.3"
composer require typo3/cms-info:"^10.4.14 || ^11.5.3"
