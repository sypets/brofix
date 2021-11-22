#!/bin/bash

# Convenience script to run CI tests locally
# default: PHP 7.4 and composer latest (uses TYPO3 v11)

# abort on error
set -e
set -x

php="7.4"
m="composerInstallMax"
vt3="11"
cleanup=0

# -------------------
# automatic variables
# -------------------
prevdir=$(pwd)
thisdir=$(dirname $0)
cd $thisdir
thisdir=$(pwd)
cd $prevdir
progname=$(basename $0)

usage()
{
    echo "[-p <PHP version>] [-m <min|max>] [-t <10|11> [-h] [-c]"
    echo " -c : runs cleanup after"
    echo " -t : TYPO3 version, can be 10 or 11, 11 is default"
    exit 1
}

while getopts "hp:m:ct:" opt;do
  case $opt in
    p)
      php=${OPTARG}
      ;;
    t)
      vt3=${OPTARG}
      ;;
    h)
      usage
      ;;
    c)
      cleanup=1
      ;;
    m)
      level=${OPTARG}
      if [[ $level == min ]];then
        m="composerInstallMin"
      fi
      ;;
    \?)
      echo "invalid option"
      usage
      ;;
  esac
done
shift $((OPTIND-1))

echo "Running with PHP=$php and $m"

# Run this in case we need test traits (see EXT:redirects_helper)
#   we need to get typo3/cms-core as source to get the Tests dir with Test traits
# echo "Tests: prepare"
# echo "Install typo3/cms-core as source. Modifies composer.json! (config:preferred-install)"
# composer config preferred-install.typo3/cms-core source

if [[ $vt3 == 10 ]];then
    composer require typo3/cms-backend:^10.4.21
    composer require typo3/cms-core:^10.4.21
    composer require typo3/cms-fluid:^10.4.21
    composer require typo3/cms-info:^10.4.21
fi

echo "composer install"
Build/Scripts/runTests.sh -p ${php} -s ${m}

echo "cgl"
Build/Scripts/runTests.sh -p ${php} -s cgl -n

echo "composer validate"
Build/Scripts/runTests.sh -p ${php} -s composerValidate

echo "lint"
Build/Scripts/runTests.sh -p ${php} -s lint

echo "phpstan"
Build/Scripts/runTests.sh -p ${php} -s phpstan -e "-c ../phpstan.neon"

echo "Unit tests"
Build/Scripts/runTests.sh -p ${php} -s unit

echo "functional tests"
Build/Scripts/runTests.sh -p ${php} -d mariadb -s functional

echo "cleanup"
echo "remove preferred-install from composer.json"
composer config --unset preferred-install

set +x

if [ $cleanup -eq 1 ];then
    $thisdir/cleanup.sh
else
    echo "--------------------------------------------------------------------------------"
    echo "!!!! Make sure to run Build/Script/cleanup.sh to revert changes to composer.json"
    git diff composer.json
    echo "--------------------------------------------------------------------------------"
fi

echo "done: ok"
