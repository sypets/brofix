#!/usr/bin/env bash

TMP="$( cat composer.json | grep "typo3/minimal" )"
[[ "$?" -ne 1 ]] && echo "[FAIL] 'typo3/minimal' package requirement was pushed in composer.json, remove this." && exit 1

echo "[OK] composer.json clean, no 'typo3/minimal' found in composer.json" && exit 0
