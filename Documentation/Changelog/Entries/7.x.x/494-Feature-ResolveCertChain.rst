.. include:: /Includes.rst.txt

===================================
Feature - Resolve Certificate Chain
===================================

*since verion 7.1.0*

`Link to Issue 486 <https://github.com/sypets/brofix/issues/494>`

This introduces a new class StayAliveCertificateChainResolver to resolve
certificate chains. This is relevant only for external links with HTTPS.0

This class requires the package stayallive/certificate-chain-resolver (which is
now required in composer.json).

Objective: this class will fetch the entire certificate chain (and store it in
var/transient) for a domain based on the given URL. The underlying problem is
that some servers do not provide the entire certificate chain. Qualys SSL Labs
(https://www.ssllabs.com/ssltest/analyze.html) shows this as "incomplete
certificate chain" or something similar. However, the browsers will usually
resolve this by fetching and caching intermediate certificates. However the curl
library (which is usually used by Guzzle in TYPO3) will not and will fail with
curl error core 60 "Unable to resolve local issuer certificate".

There is a method called  AIA Fetching (Authority Information Access) which outlines how to fetch intermediate certs.
@see https://www.thesslstore.com/blog/aia-fetching/

Usage
=====
 By default, this class will be used in ExternalLinktype.

 Configuration is in Extension Configuration (see ext_conf_template.txt)
 resolveCertChains. By default it is set to "intelligent": the cert chain is
 only fetched on typical errors (libcurl errno=60). It can also be set
 to "always" or "never"

Impact
======

Possibly fixes problems with external links with incomple certificate chain.

URLs which had this problem and previously showed an error in brofix
(false positives) should now be displayed with status "ok".

Migration
=========

Do a rechecking on affected URLs. This is not strictly necessary, it is also
possible to wait until the cache expires for these URLs.

