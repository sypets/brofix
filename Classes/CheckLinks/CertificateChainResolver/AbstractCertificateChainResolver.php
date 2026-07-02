<?php

declare(strict_types=1);
namespace Sypets\Brofix\CheckLinks\CertificateChainResolver;

use TYPO3\CMS\Core\Core\Environment;

abstract class AbstractCertificateChainResolver implements CertificateChainResolverInterface
{
    protected string $tmpDir;

    protected function getDomainForUrl(string $url): string
    {
        return parse_url($url, PHP_URL_HOST);
    }

    protected function initializeTmpDir(): string
    {
        $tmpdir = Environment::getVarPath() . '/transient';
        if (!is_dir($tmpdir)) {
            mkdir($tmpdir);
        }
        return $tmpdir;
    }

    public function getTmpDir(): string
    {
        if (!isset($this->tmpdir)) {
            $this->initializeTmpDir();
        }
        return $this->tmpDir;
    }

    protected function getTmpDirCertFilename(string $domain): string
    {
        return $this->getTmpDir() . '/brofix_ssl_cert_' . $domain . '.crt';
    }

    protected function getTmpDirFullCertFilename(string $domain): string
    {
        return $this->getTmpDir() . '/brofix_ssl_cert_full_' . $domain . '.crt';
    }

    /**
     * This will only fetch the cert, not necessarily the full cert chain
     */
    protected function fetchCert(string $domain, string $tempCertFilename): void
    {
        stream_context_set_default([
            'http' => [
                'follow_location' => 1, // Ensure redirect following is on
                'max_redirects'   => 5, // Prevent infinite redirect loops
                'timeout'         => 10 // Prevent script from hanging indefinitely
            ]
        ]);

        // Open a secure stream connection directly to the port to capture context
        $streamContext = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer'       => false, // False only here to catch the broken leaf
                'verify_peer_name'  => false
            ]
        ]);

        $port = 443;

        $client = @stream_socket_client(
            "ssl://{$domain}:{$port}",
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $streamContext
        );

        if (!$client) {
            throw new \Exception("Failed to connect to domain: $errstr ($errno)", 7470884634);
        }

        // 3. Extract the raw peer certificate resource
        $params = stream_context_get_options($streamContext);
        $peerCertificate = $params['ssl']['peer_certificate'] ?? '';
        $capture_peer_cert = (bool)($params['ssl']['capture_peer_cert'] ?? false);

        if (!$capture_peer_cert) {
            throw new \Exception("Could not capture the SSL leaf certificate from {$domain}.", 3700325786);
        }

        // 4. Convert the native PHP resource into a PEM string format
        openssl_x509_export($peerCertificate, $pemStringContents);

        // save to file
        file_put_contents($tempCertFilename, $pemStringContents);
    }
}
