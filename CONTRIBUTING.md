# Contributing

Contributions are welcome. Please open issues to report bugs and
create PR to propose changes.

A similar testing workflow to the TYPO3 core is used. However, it
is currently not as extensive as the core, but the following is
checked:

1. Coding guidelines (CGL)
2. lint
3. unit tests
4. functional tests

For more information, see the file .github/workflows/tests.yml and
the documentation on https://docs.typo3.org/m/typo3/reference-coreapi/10.4/en-us/Testing/ExtensionTesting.html.

## CGL

Please adhere to the TYPO3 core coding Guidelines.

Use .editorconfig in this directory.

You can check for (and automatically fix) cgl violations.

Check in .github/workflows for how to run the tests, e.g.
run this once. It will setup the .Build directory and create composer.lock:

```
Build/Scripts/runTests.sh -p 7.4 -s composerInstallMax
```


Use runTests.sh with the -v option (for verbose).

Check all PHP files (dry-run, do not fix):

```
Build/Scripts/runTests.sh -s cgl -n -v
```

Check **and fix** CGL in PHP files:

```
Build/Scripts/cglFixMyCommit.sh -s cgl -v
```

See all options:

```
Build/Scripts/runTests.sh -h
```

Cleanup:

```
rm -rf .Build;rm composer.lock;composer config --unset platform.php;composer config --unset platform
```
