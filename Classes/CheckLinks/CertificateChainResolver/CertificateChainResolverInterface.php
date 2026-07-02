<?php

declare(strict_types=1);
namespace Sypets\Brofix\CheckLinks\CertificateChainResolver;

/**
 * This interface handles fetching certiciate chains for a domain.
 *
 *  Objective: Implementing classes will fetch the entire certificate chain (and store it in var/transient) for a domain based on
 *  the given URL. The underlying problem is that some servers do not provide the entire certificate chain. Qualys SSL Labs
 *  (https://www.ssllabs.com/ssltest/analyze.html) shows this as "incomplete certificate chain" or something similar.
 *  However, the browsers will usually resole this by fetching and caching intermediate certificates. However the curl
 *  library (which is usually used by Guzzle in TYPO3) will not and will fail with curl error core 60 "Unable to resolve
 *  local issuer certificate".
 *
 *  There is a method called  AIA Fetching (Authority Information Access) which outlines how to fetch intermediate certs.
 * @see https://www.thesslstore.com/blog/aia-fetching/
 *
 * Usage: brofix will by default use the supplied implementation StayAliveCertificateChainResolver. You can provide
 * a different implementation and must the override ExternalLinkType to use that.
 *
 * Configuration is in Extension Configuration (see ext_conf_template.txt) resolveCertChains. By default it is set
 * to "intelligent", then the cert chain is only fetched on typical errors (libcurl errno=60). It can also be set
 * to "always" or "never"
 */
interface CertificateChainResolverInterface
{
    /**
     * Resolves cert chain, writes to file and returns path name
     */
    public function resolveCertChain(string $url, bool $onlyForHttpsUrls = false): string;
}
