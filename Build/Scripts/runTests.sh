#!/usr/bin/env bash

# loosely based on Build/Script files in TYPO3 core


# config
SUPPORTED_PHP_VERSIONS="8.1|8.2|8.3|8.4|8.5"
DEFAULT_PHP_VERSION="8.4"
PHP_VERSION="${DEFAULT_PHP_VERSION}"
DEFAULT_PHP_PLATFORM_VERSION="8.4.10"


# Function to write a .env file in Build/testing-docker/local
# This is read by docker-compose and vars defined here are
# used in Build/testing-docker/local/docker-compose.yml
setUpDockerComposeDotEnv() {
    # Delete possibly existing local .env file if exists
    [ -e .env ] && rm .env
    # Set up a new .env file for docker-compose
    {
        echo "COMPOSE_PROJECT_NAME=local"

        # To prevent access rights of files created by the testing, the docker image later
        # runs with the same user that is currently executing the script. docker-compose can't
        # use $UID directly itself since it is a shell variable and not an env variable, so
        # we have to set it explicitly here.
        echo "HOST_UID=`id -u`"

        # Your local home directory for composer and npm caching
        echo "HOST_HOME=${HOME}"

        # Your local user
        echo "CORE_VERSION=${CORE_VERSION}"
        echo "ROOT_DIR=${ROOT_DIR}"
        echo "HOST_USER=${USER}"
        echo "TEST_FILE=${TEST_FILE}"
        echo "CGLCHECK_DRY_RUN=${CGLCHECK_DRY_RUN}"
        echo "PHP_XDEBUG_ON=${PHP_XDEBUG_ON}"
        echo "PHP_XDEBUG_PORT=${PHP_XDEBUG_PORT}"
        echo "PHP_VERSION=${PHP_VERSION}"
        echo "DEFAULT_PHP_VERSION"=${DEFAULT_PHP_VERSION}
        echo "DEFAULT_PHP_PLATFORM_VERSION"=${DEFAULT_PHP_PLATFORM_VERSION}
        echo "PHP_PLATFORM_VERSION"=${PHP_PLATFORM_VERSION}
        echo "MARIADB_VERSION=${MARIADB_VERSION}"
        echo "MYSQL_VERSION=${MYSQL_VERSION}"
        echo "POSTGRES_VERSION=${POSTGRES_VERSION}"
        echo "DOCKER_PHP_IMAGE=${DOCKER_PHP_IMAGE}"
        echo "EXTRA_TEST_OPTIONS=${EXTRA_TEST_OPTIONS}"
        echo "SCRIPT_VERBOSE=${SCRIPT_VERBOSE}"
        echo "PASSWD_PATH=${PASSWD_PATH}"
        echo "IMAGE_PREFIX=${IMAGE_PREFIX}"
        echo "DOCKER_COMPOSE_COMMAND=${DOCKER_COMPOSE_COMMAND}"

    } > .env
}

# Load help text into $HELP
read -r -d '' HELP <<EOF
brofix test runner. Execute unit, functional and other test suites in
a docker based test environment. Handles execution of single test files, sending
xdebug information to a local IDE and more.
Also used by github actions for test execution.

Usage: $0 [options] [file]

No arguments: Run all unit tests with default PHP version

Options:
    -s <...>
        Specifies which test suite to run
            - composerInstall: "composer install"
            - composerInstallMax: "composer update", with no platform.php config.
            - composerInstallMin: "composer update --prefer-lowest", with platform.php set to PHP version x.x.0.
            - composerValidate: "composer validate"
            - composerCoreVersion: "composer require --no-install typo3/minimal:"coreVersion"
            - cgl: test and fix all core php files
            - cglGit: test and fix latest committed patch for CGL compliance
            - lint: PHP linting
            - phpstan: phpstan tests
            - phpstanGenerateBaseline: regenerate phpstan baseline, handy after phpstan updates
            - unit (default): PHP unit tests
            - functional: functional tests
            - rector:check : check rector (dry-run)
            - rector:fix   : apply rector
            - rector:baseline : create baseline file

    -t <composer-core-version-constraint>
        Only with -s composerCoreVersion
        Specifies the Typo3 core version to be used
            - '^11.5' (default)
            - ...

    -d <mariadb|mysql|postgres|sqlite>
        Only with -s functional
        Specifies on which DBMS tests are performed
            - mariadb (default): use mariadb
            - mysql: MySQL (currently untested)
            - postgres: use postgres
            - sqlite: use sqlite

    -p <${SUPPORTED_PHP_VERSIONS}>
        Specifies the PHP minor version to be used

    -e "<phpunit|phpstan options>"
        Only with -s functional|unit|phpstan
        Additional options to send to phpunit tests.
        For phpunit, options starting with "--" must be added after options starting with "-".
        Example -e "-v --filter canRetrieveValueWithGP" to enable verbose output AND filter tests
        named "canRetrieveValueWithGP"

    -x
        Only with -s unit | function
        Send information to host instance for test or system under test break points. This is especially
        useful if a local PhpStorm instance is listening on default xdebug port 9003. A different port
        can be selected with -y

    -y <port>
        Send xdebug information to a different port than default 9003 if an IDE like PhpStorm
        is not listening on default port.

    -n
        Only with -s cgl|cglGit
        Activate dry-run in CGL check that does not actively change files and only prints broken ones.

    -u
        Update existing ${TYPO3_DOCKER_IMAGE_BASE}XY:latest docker images. Maintenance call to docker pull latest
        versions of the main php images. The images are updated once in a while and only the youngest
        ones are supported by core testing. Use this if weird test errors occur. Also removes obsolete
        image versions of ${TYPO3_DOCKER_IMAGE_BASE}XY.

    -v
        Enable verbose script output. Shows variables and docker commands.

    -h
        Show this help.

Examples:
    # Run unit tests using default PHP
    ./Build/Scripts/runTests.sh

    # Run unit tests using PHP 8.1
    ./Build/Scripts/runTests.sh -p 8.1

    # Run functional tests using sqlite
    ./Build/Scripts/runTests.sh -s functional -d sqlite

    # Run functional tests in phpunit with a filtered test method name in a specified file and xdebug enabled.
    ./Build/Scripts/runTests.sh -s functional -x -e "--filter getLinkStatisticsFindOnlyPageBrokenLinks" Tests/Functional/LinkAnalyzerTest.php
EOF

# Test if docker exists, else exit out with error
if ! type "docker" > /dev/null; then
  echo "This script relies on docker. Please install" >&2
  exit 1
fi

# Go to the directory this script is located, so everything else is relative
# to this dir, no matter from where this script is called.
THIS_SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null && pwd )"
cd "$THIS_SCRIPT_DIR" || exit 1

# Go to directory that contains the local docker-compose.yml file
cd ../testing-docker || exit 1

# Option defaults
if ! command -v realpath &> /dev/null; then
  echo "This script works best with realpath installed" >&2
  ROOT_DIR="${PWD}/../../"
else
  ROOT_DIR=`realpath ${PWD}/../../`
fi
CORE_VERSION="13.4"
TEST_SUITE="unit"
DBMS="mariadb"
PHP_VERSION="$DEFAULT_PHP_VERSIONS"
DATABASE_DRIVER=""
MARIADB_VERSION="10.3"
MYSQL_VERSION="8.0"
POSTGRES_VERSION="10"
PHP_XDEBUG_ON=0
PHP_XDEBUG_PORT=9003
EXTRA_TEST_OPTIONS=""
SCRIPT_VERBOSE=0
CGLCHECK_DRY_RUN=""
PASSWD_PATH=/etc/passwd
IMAGE_PREFIX="ghcr.io/typo3/"
DOCKER_COMPOSE_COMMAND="docker-compose"
which docker-compose 2>/dev/null >/dev/null
if [ $? -ne 0 ];then
     DOCKER_COMPOSE_COMMAND="docker compose"
fi


# Option parsing
# Reset in case getopts has been used previously in the shell
OPTIND=1
# Array for invalid options
INVALID_OPTIONS=();
# Simple option parsing based on getopts (! not getopt)
while getopts ":s:t:d:p:e:xy:huvn" OPT; do
    case ${OPT} in
        s)
            TEST_SUITE=${OPTARG}
            ;;
        t)
            CORE_VERSION=${OPTARG}
            ;;
        d)
            DBMS=${OPTARG}
            ;;
        p)
            PHP_VERSION=${OPTARG}
            if ! [[ ${PHP_VERSION} =~ ^(${SUPPORTED_PHP_VERSIONS})$ ]]; then
                INVALID_OPTIONS+=("${OPT} ${OPTARG} : unsupported php version")
            fi
            ;;
        e)
            EXTRA_TEST_OPTIONS=${OPTARG}
            ;;
        x)
            PHP_XDEBUG_ON=1
            ;;
        y)
            PHP_XDEBUG_PORT=${OPTARG}
            ;;
        h)
            echo "${HELP}"
            exit 0
            ;;
        n)
            CGLCHECK_DRY_RUN="-n"
            ;;
        u)
            TEST_SUITE=update
            ;;
        v)
            SCRIPT_VERBOSE=1
            ;;
        \?)
            INVALID_OPTIONS+=("${OPTARG}")
            ;;
        :)
            INVALID_OPTIONS+=("${OPTARG}")
            ;;
    esac
done

# Exit on invalid options
if [ ${#INVALID_OPTIONS[@]} -ne 0 ]; then
    echo "Invalid option(s):" >&2
    for I in "${INVALID_OPTIONS[@]}"; do
        echo "-"${I} >&2
    done
    echo >&2
    echo "${HELP}" >&2
    exit 1
fi

# Move "7.2" to "php72", the latter is the docker container name
DOCKER_PHP_IMAGE=`echo "php${PHP_VERSION}" | sed -e 's/\.//'`

# Set $1 to first mass argument, this is the optional test file or test directory to execute
shift $((OPTIND - 1))
if [ -n "${1}" ]; then
    TEST_FILE="../${1}"
else
    case ${TEST_SUITE} in
        functional)
            TEST_FILE="../Tests/Functional"
            ;;
        unit)
            TEST_FILE="../Tests/Unit"
            ;;
    esac
fi

if [ ${SCRIPT_VERBOSE} -eq 1 ]; then
    set -x
fi

# Suite execution
case ${TEST_SUITE} in
    composerCoreVersion)
        setUpDockerComposeDotEnv
        ${DOCKER_COMPOSE_COMMAND} run composer_coreversion_require
        SUITE_EXIT_CODE=$?
        ;;
    composerInstall)
        setUpDockerComposeDotEnv
        ${DOCKER_COMPOSE_COMMAND} run composer_install
        SUITE_EXIT_CODE=$?
        ${DOCKER_COMPOSE_COMMAND} down
        ;;
    composerInstallMax)
        setUpDockerComposeDotEnv
        ${DOCKER_COMPOSE_COMMAND} run composer_install_max
        SUITE_EXIT_CODE=$?
        ${DOCKER_COMPOSE_COMMAND} down
        ;;
    composerInstallMin)
        setUpDockerComposeDotEnv
        ${DOCKER_COMPOSE_COMMAND} run composer_install_min
        SUITE_EXIT_CODE=$?
        ${DOCKER_COMPOSE_COMMAND} down
        ;;
    composerValidate)
        setUpDockerComposeDotEnv
        ${DOCKER_COMPOSE_COMMAND} run composer_validate
        SUITE_EXIT_CODE=$?
        ${DOCKER_COMPOSE_COMMAND} down
        ;;
    cgl)
        # Active dry-run for cglAll needs not "-n" but specific options
        if [[ ! -z ${CGLCHECK_DRY_RUN} ]]; then
            CGLCHECK_DRY_RUN="--dry-run --diff"
        fi
        setUpDockerComposeDotEnv
        ${DOCKER_COMPOSE_COMMAND} run cgl_all
        SUITE_EXIT_CODE=$?
        ${DOCKER_COMPOSE_COMMAND} down
        ;;
    cglGit)
        # Active dry-run for cglAll needs not "-n" but specific options
        setUpDockerComposeDotEnv
        ${DOCKER_COMPOSE_COMMAND} run cgl_git
        SUITE_EXIT_CODE=$?
        ${DOCKER_COMPOSE_COMMAND} down
        ;;
    rector:check)
        setUpDockerComposeDotEnv
        ${DOCKER_COMPOSE_COMMAND} run rector_check
        SUITE_EXIT_CODE=$?
        ${DOCKER_COMPOSE_COMMAND} down
        ;;
    rector:fix)
        setUpDockerComposeDotEnv
        ${DOCKER_COMPOSE_COMMAND} run rector_fix
        SUITE_EXIT_CODE=$?
        ${DOCKER_COMPOSE_COMMAND} down
        ;;
    rector:baseline)
        setUpDockerComposeDotEnv
        ${DOCKER_COMPOSE_COMMAND} run rector_baseline
        SUITE_EXIT_CODE=$?
        ${DOCKER_COMPOSE_COMMAND} down
        ;;
functional)
        setUpDockerComposeDotEnv
        case ${DBMS} in
            mariadb)
                ${DOCKER_COMPOSE_COMMAND} run functional_mariadb
                SUITE_EXIT_CODE=$?
                ;;
            mysql)
                ${DOCKER_COMPOSE_COMMAND} run functional_mysql
                SUITE_EXIT_CODE=$?
                ;;
            postgres)
                ${DOCKER_COMPOSE_COMMAND} run functional_postgres
                SUITE_EXIT_CODE=$?
                ;;
            sqlite)
                # sqlite has a tmpfs as typo3temp/var/tests/functional-sqlite-dbs/
                # Since docker is executed as root (yay!), the path to this dir is owned by
                # root if docker creates it. Thank you, docker. We create the path beforehand
                # to avoid permission issues on host filesystem after execution.
                mkdir -p "${ROOT_DIR}/.Build/Web/typo3temp/var/tests/functional-sqlite-dbs/"
                ${DOCKER_COMPOSE_COMMAND} run functional_sqlite
                SUITE_EXIT_CODE=$?
                ;;
            *)
                echo "Invalid -d option argument ${DBMS}" >&2
                echo >&2
                echo "${HELP}" >&2
                exit 1
        esac
        ${DOCKER_COMPOSE_COMMAND} down
        ;;
    lint)
        setUpDockerComposeDotEnv
        ${DOCKER_COMPOSE_COMMAND} run lint
        SUITE_EXIT_CODE=$?
        ${DOCKER_COMPOSE_COMMAND} down
        ;;
    phpstan)
        setUpDockerComposeDotEnv
        ${DOCKER_COMPOSE_COMMAND} run phpstan
        SUITE_EXIT_CODE=$?
        ${DOCKER_COMPOSE_COMMAND} down
        ;;
    phpstanGenerateBaseline)
        setUpDockerComposeDotEnv
        ${DOCKER_COMPOSE_COMMAND} run phpstan_generate_baseline
        SUITE_EXIT_CODE=$?
        ${DOCKER_COMPOSE_COMMAND} down
        ;;
    unit)
        setUpDockerComposeDotEnv
        ${DOCKER_COMPOSE_COMMAND} run unit
        SUITE_EXIT_CODE=$?
        ${DOCKER_COMPOSE_COMMAND} down
        ;;
    update)
       # prune unused, dangling local volumes
       echo "> prune unused, dangling local volumes"
       docker volume ls -q -f driver=local -f dangling=true | awk '$0 ~ /^[0-9a-f]{64}$/ { print }' | xargs -I {} docker volume rm {}
       echo ""
       # pull typo3/core-testing-*:latest versions of those ones that exist locally
       echo "> pull ${IMAGE_PREFIX}core-testing-*:latest versions of those ones that exist locally"
       docker images ${IMAGE_PREFIX}core-testing-*:latest --format "{{.Repository}}:latest" | xargs -I {} docker pull {}
       echo ""
       # remove "dangling" typo3/core-testing-* images (those tagged as <none>)
       echo "> remove \"dangling\" ${IMAGE_PREFIX}core-testing-* images (those tagged as <none>)"
       docker images ${IMAGE_PREFIX}core-testing-* --filter "dangling=true" --format "{{.ID}}" | xargs -I {} docker rmi {}
       echo ""
       ;;
    *)
        echo "Invalid -s option argument ${TEST_SUITE}" >&2
        echo >&2
        echo "${HELP}" >&2
        exit 1
esac

exit $SUITE_EXIT_CODE
