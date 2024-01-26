<?php

declare(strict_types=1);
namespace Sypets\Brofix\Command;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;

class CommandUtility
{
    /**
     * This is needed to create a link to the backend properly.
     * reused from
     * @see TYPO3\CMS\Backend\Command\ResetPasswordCommand
     */
    public static function createFakeWebRequest(string $backendUrl): ServerRequestInterface
    {
        $uri = new Uri($backendUrl);
        $request = new ServerRequest(
            $uri,
            'GET',
            'php://input',
            [],
            [
                'HTTP_HOST' => $uri->getHost(),
                'SERVER_NAME' => $uri->getHost(),
                'HTTPS' => $uri->getScheme() === 'https',
                'SCRIPT_FILENAME' => __FILE__,
                'SCRIPT_NAME' => rtrim($uri->getPath(), '/') . '/',
            ]
        );
        $backedUpEnvironment = self::simulateEnvironmentForBackendEntryPoint();
        $normalizedParams = NormalizedParams::createFromRequest($request);

        // Restore the environment
        Environment::initialize(
            Environment::getContext(),
            Environment::isCli(),
            Environment::isComposerMode(),
            Environment::getProjectPath(),
            Environment::getPublicPath(),
            Environment::getVarPath(),
            Environment::getConfigPath(),
            $backedUpEnvironment['currentScript'],
            Environment::isWindows() ? 'WINDOWS' : 'UNIX'
        );

        return $request
            ->withAttribute('normalizedParams', $normalizedParams)
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
    }

    /**
     * This is a workaround to use "PublicPath . /typo3/index.php" instead of "publicPath . /typo3/sysext/core/bin/typo3"
     * so the the web root is detected properly in normalizedParams.
     *
     * @return array<string,mixed>
     * reused from
     * @see TYPO3\CMS\Backend\Command\ResetPasswordCommand
     */
    public static function simulateEnvironmentForBackendEntryPoint(): array
    {
        $currentEnvironment = Environment::toArray();
        Environment::initialize(
            Environment::getContext(),
            Environment::isCli(),
            Environment::isComposerMode(),
            Environment::getProjectPath(),
            Environment::getPublicPath(),
            Environment::getVarPath(),
            Environment::getConfigPath(),
            // This is ugly, as this change fakes the directory
            dirname(Environment::getCurrentScript(), 4) . DIRECTORY_SEPARATOR . 'index.php',
            Environment::isWindows() ? 'WINDOWS' : 'UNIX'
        );
        return $currentEnvironment;
    }
}
