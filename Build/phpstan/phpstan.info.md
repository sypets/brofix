# Adding a baseline

The goal is to fix the code. However, sometimes fix cannot be done because of backwards
compatibility, thus errors can be added to a baseline file. The baseline file contains
ignorePatters to silence errors.

Adding new ignore patterns should be done with care. Thus checking baseline file
is required, even if baseline regenerate is run because of fixed errors.

To update baseline use: `Build/Scripts/runTests.sh -s phpstanGenerateBaseline`

If code get fixed which was silenced through an ignorePattern, phpstan check
would complain with a corresponding error.

- Ignore Patterns did not match: Fully solved and can be removed, regenerate baseline to do this.
- Ignore Pattern count do not match: Decreasing counts are issue solved, thus regenerate baseline. Increse should be checked if it can be directly fixed, otherwise update baseline.


Points which should be kept in mind:
* Never ever edit manually the baseline file (will be overriden)
* Do not add manually ignore patterns to the main config file OR a further file. Use baseline rebuild.

# Some notes

## Dealing with mixed arrays

Be as specific as possible, e.g. use

* `array<string>` etc.
* or `array{'foo': int, "bar": string}`

If the array is dynamic or cannot be specified, use `mixed[]`

see

* https://phpstan.org/writing-php-code/phpdoc-types#array-shapes
* https://phpstan.org/blog/solving-phpstan-no-value-type-specified-in-iterable-type
* https://github.com/phpstan/phpstan/discussions/4375
