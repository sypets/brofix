#!/bin/bash

# Convenience script to run CI tests locally
# default: PHP 8.1 and composer latest (uses TYPO3 v11)

# abort on error
set -e
set -x

# --------
# defaults
# --------
PHP_VERSION_DEFAULT="8.1"
INSTALL_MIN_MAX_DEFAULT="composerInstallMax"
TYPO3_VERSION_DEFAULT="11"


# -------------
# used settings
# -------------
# PHP version
php=${PHP_VERSION_DEFAULT}
# composerInstallMin | composerInstallMax
m=${INSTALL_MIN_MAX_DEFAULT}
# TYPO3 version
vt3=${TYPO3_VERSION_DEFAULT}
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
    echo "[-p <PHP version>] [-m <min|max>] [-t <11> [-h] [-c]"
    echo " -c : runs cleanup after"
    echo " -t : TYPO3 version, ${PHP_VERSION_DEFAULT} is default"
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

echo "Running with PHP version${php} an TYPO3 version ${vt3} using ${m}"
sleep 5

# Run this in case we need test traits (see EXT:redirects_helper)
#   we need to get typo3/cms-core as source to get the Tests dir with Test traits
# echo "Tests: prepare"
# echo "Install typo3/cms-core as source. Modifies composer.json! (config:preferred-install)"
# composer config preferred-install.typo3/cms-core source

if [[ $vt3 == 11 ]];then
    Build/Scripts/runTests.sh -p ${php} -t "^11.5" -s composerCoreVersion
else
    echo "wrong TYPO3 version"
    exit 1
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
Build/Scripts/runTests.sh -p ${php} -s phpstan

echo "Unit tests"
Build/Scripts/runTests.sh -p ${php} -s unit

echo "functional tests"
Build/Scripts/runTests.sh -p ${php} -d mariadb -s functional

# -------
# cleanup
# -------

echo "cleanup"
echo "remove preferred-install from composer.json"
composer config --unset preferred-install
composer remove typo3/minimal

if [ $cleanup -eq 1 ];then
    $thisdir/cleanup.sh
else
    echo "--------------------------------------------------------------------------------"
    echo "!!!! Make sure to revert changes to composer.json e.g. by git checkout composer.json"
    git diff composer.json
    echo "--------------------------------------------------------------------------------"
fi

# check if changes in composer.json
Build/Scripts/checkComposerJsonForPushedMinimalPackage.sh

echo "done: ok"
