<?php

declare(strict_types=1);
namespace Sypets\Brofix\CheckLinks\CertificateChainResolver;

use TYPO3\CMS\Core\Core\Environment;

/**
 * This class handles fetching certiciate chains for a domain.
 *
 * This class requires the package:
 * stayallive/certificate-chain-resolver
 *
 *  Objective: this class will fetch the entire certificate chain (and store it in var/transient) for a domain based on
 *  the given URL. The underlying problem is that some servers do not provide the entire certificate chain. Qualys SSL Labs
 *  (https://www.ssllabs.com/ssltest/analyze.html) shows this as "incomplete certificate chain" or something similar.
 *  However, the browsers will usually resolve this by fetching and caching intermediate certificates. However the curl
 *  library (which is usually used by Guzzle in TYPO3) will not and will fail with curl error core 60 "Unable to resolve
 *  local issuer certificate".
 *
 *  There is a method called  AIA Fetching (Authority Information Access) which outlines how to fetch intermediate certs.
 * @see https://www.thesslstore.com/blog/aia-fetching/
 *
 * Usage:
 *   By default, this class will be used in ExternalLinktype.
 *
 *   Configuration is in Extension Configuration (see ext_conf_template.txt) resolveCertChains. By default it is set
 *   to "intelligent", then the cert chain is only fetched on typical errors (libcurl errno=60). It can also be set
 *   to "always" or "never"
 *
 * @todo Should we follow redirects and possibly get certificates for the targets as well?
 */
class StayAliveCertificateChainResolver extends AbstractCertificateChainResolver
{
    /**
     * Fetch cert chain for URL and store to local path
     */
    public function resolveCertChain(string $url, bool $onlyForHttpsUrls = false): string
    {
        if (!class_exists('\Stayallive\CertificateChain\Certificate')
            || !class_exists('\Stayallive\CertificateChain\Resolver')) {
            return '';
        }

        if ($onlyForHttpsUrls && !str_starts_with($url, 'https://')) {
            // no https, do nothing
            return '';
        }
        $domain =  $this->getDomainForUrl($url);

        $tmpdir = $this->getTmpDir();

        $tempCertFilename = $this->getTmpDirCertFilename($domain);
        $tempCertfullChainFilename = $this->getTmpDirFullCertFilename($domain);

        if (file_exists($tempCertfullChainFilename)) {
            return $tempCertfullChainFilename;
        }

        $this->fetchCert($domain, $tempCertFilename);

        $this->resolveCert($domain, $tempCertFilename, $tempCertfullChainFilename);

        return $tempCertfullChainFilename;
    }

    protected function resolveCert(string $domain, string $tempCertFilename, string $tempCertfullChainFilename): void
    {
        // 5. Load the PEM string instead of the URL string directly into the resolver
        $certificate = \Stayallive\CertificateChain\Certificate::loadFromPathOrUrl($tempCertFilename);
        $resolver = new \Stayallive\CertificateChain\Resolver($certificate);

        // 6. Get your finalized complete chain
        $fullChainText = $resolver->getContents();

        // 7. Save locally for your Guzzle application environment
        file_put_contents($tempCertfullChainFilename, $fullChainText);
    }
}
