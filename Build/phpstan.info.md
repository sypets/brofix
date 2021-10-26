# Adding a baseline

-  baseline: this ignores some errors
-  the goal is to fix all problems that can be fixed and remove the error messages from this file

create:

1. Increase level in phpstan.neon
2. cd .Build; bin/phpstan analyze  --configuration ../Build/phpstan.neon ../Classes --generate-baseline
3. cp phpstan.baseline.neon ../Build/phpstan-baseline-level$LEVEL.neon

# Dealing with mixed arrays

Be as specific as possible, e.g. use

* `array<string>` etc. 
* or `array{'foo': int, "bar": string}`

If the array is dynamic or cannot be specified, use `mixed[]`

see

* https://phpstan.org/writing-php-code/phpdoc-types#array-shapes
* https://phpstan.org/blog/solving-phpstan-no-value-type-specified-in-iterable-type
* https://github.com/phpstan/phpstan/discussions/4375
