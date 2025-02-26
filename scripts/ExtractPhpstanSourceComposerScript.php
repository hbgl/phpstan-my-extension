<?php
declare(strict_types=1);

namespace Hbgl\PhpstanMyExtensionScripts;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Installer\PackageEvent;
use Composer\Package\PackageInterface;
use Composer\Util\Filesystem;
use Phar;
use RuntimeException;

class ExtractPhpstanSourceComposerScript
{
    private function __construct(
        private readonly Composer $composer,
        private readonly PackageInterface $package,
    ) {
    }

    public static function postPackageInstall(PackageEvent $event): void
    {
        $operation = $event->getOperation();
        if ($operation instanceof InstallOperation) {
            $instance = new self($event->getComposer(), $operation->getPackage());
            $instance->run();
        }
    }

    public static function postPackageUpdate(PackageEvent $event): void
    {
        $operation = $event->getOperation();
        if ($operation instanceof UpdateOperation) {
            $instance = new self($event->getComposer(), $operation->getTargetPackage());
            $instance->run();
        }
    }

    private function run(): void
    {
        if ($this->package->getName() !== 'phpstan/phpstan') {
            return;
        }

        $pharPath = $this->findPharPath();
        $extractDir = $this->findExtractionPath();

        $fs = new Filesystem();
        $fs->remove($extractDir);
        $fs->ensureDirectoryExists($extractDir);

        $phar = new Phar($pharPath);
        $phar->extractTo($extractDir, 'src/');
    }

    private function findPharPath(): string
    {
        $installPath = $this->composer->getInstallationManager()->getInstallPath($this->package);
        if ($installPath === null) {
            throw new RuntimeException('Unable to determine phpstan installation path');
        }

        $pharPath = realpath("{$installPath}/phpstan.phar");
        if (! $pharPath) {
            throw new RuntimeException("File phpstan.phar not found. Tried: {$installPath}/phpstan.phar");
        }

        return $pharPath;
    }

    private function findExtractionPath(): string
    {
        $vendorDir = $this->composer->getConfig()->get('vendor-dir');
        if (! is_string($vendorDir) || $vendorDir === '') {
            throw new RuntimeException('Composer config key vendor-dir is not set');
        }

        $baseDir = realpath("{$vendorDir}/../");
        if (! $baseDir) {
            throw new RuntimeException('Unable to determine project base directory');
        }

        return "{$baseDir}/.ideincludes/phpstan/phpstan";
    }
}
