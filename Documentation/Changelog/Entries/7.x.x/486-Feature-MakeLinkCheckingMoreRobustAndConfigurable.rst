.. include:: /Includes.rst.txt

========================================
Feature - Make link checking more robust
========================================

*since verion 7.0.1*

`Link to Issue 486 <https://github.com/sypets/brofix/issues/486>`

Make link checking more robust and make it configurable how to handle exceptions

Link checking may abort because an exception is thrown. For some severe errors,
this makes sense, but there are some scenarios with minor errors where the
checking should continue.

For example, if a record is deleted between fetching several records
in a  database query and TCA parsing, this may result in an exception in
LinkParser.

Brofix always tries to perform robust checking so that small errors are just
logged via the logging framework, but it is possible to change this behavior
with the option behaviourOnCheckError in Extension Configuration:

1. (default) **intelligent** : mode where brofix decides on a case by case basis
2. **continue** : continue checking even in case of exceptions due to errors like these (which might obscure errors)
3. **abort** : always throw exception


Impact
======

*  Possibly fixes problems with brofix aborting while checking links due to an
   exception.

Migration
=========

It is not necesary to change anything unless the default behaviour is not to
be used. In this case change the option behaviourOnCheckError in Extension
Configuration.
