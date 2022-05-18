<?php

namespace ComposerOverrideFiles;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Script\ScriptEvents;
use Composer\Script\Event;
use Composer\Plugin\PluginInterface;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * @var \Composer\Composer
     */
    protected $composer;

    /**
     * @var IOInterface
     */
    protected $io;

    /**
     * @var array
     */
    protected $extraConfig;

    /**
     * Apply plugin modifications to Composer
     *
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
        $extraConfig = $this->composer->getPackage()->getExtra();
        if (!isset($extraConfig['override_files'])) {
            $this->extraConfig = [];
        } else {
            $this->extraConfig = $extraConfig['override_files'];
        }
    }

    /**
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function deactivate(Composer $composer, IOInterface $io)
    {
        // do nothing
    }

    /**
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function uninstall(Composer $composer, IOInterface $io)
    {
        // do nothing
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            ScriptEvents::PRE_AUTOLOAD_DUMP => 'preAutoloadDump',
        );
    }

    public function preAutoloadDump()
    {
        $this->validate();

        $overrideFiles = OverrideFile::getOverrideFiles($this->composer, $this->io, $this->getConfig('path'), $this->getConfig('base_vendor_dir'));

        $autoloads = $this->composer->getPackage()->getAutoload();

        foreach ($overrideFiles as $overrideFile) {
            $autoloads['exclude-from-classmap'][] = $overrideFile->getVendorFile();
            $autoloads['files'][] = $overrideFile->getOverrideFile();

            $this->io->write("Overrided file: <info>{$overrideFile->getVendorFile(true)}</info> => <info>{$overrideFile->getOverrideFile(true)}</info>");
        }

        $this->composer->getPackage()->setAutoload($autoloads);
    }

    private function validate()
    {
        $filesystem = new \Composer\Util\Filesystem();
        $rootPath = $filesystem->normalizePath(realpath(realpath(getcwd())));
        $vendorPath = $filesystem->normalizePath(realpath(realpath($this->composer->getConfig()->get('vendor-dir'))));

        $path = self::joinPaths($rootPath, $this->getConfig('path'));
        $baseVendorDir = self::joinPaths($vendorPath, $this->getConfig('base_vendor_dir'));

        if (empty($path)) {
            $this->io->writeError("<error>Override file directory cannot be empty</error>", true);
            exit(1);
        }

        if (false === file_exists($path)) {
            $this->io->writeError("<error>Override file directory ($path) doesn't exists</error>", true);
            exit(1);
        }

        if (!empty($baseVendorDir)) {
            if (false === file_exists($baseVendorDir)) {
                $this->io->writeError("<error>Override file base directory ($baseVendorDir) doesn't exists</error>", true);
                exit(1);
            }
        }
    }

    public static function joinPaths()
    {
        $filesystem = new \Composer\Util\Filesystem();
        return $filesystem->normalizePath(implode(DIRECTORY_SEPARATOR, func_get_args()));
    }

    /**
     * @param string $key
     * @param mixed|null $default
     * @return mixed|null
     */
    private function getConfig($key, $default = null)
    {
        if (!isset($this->extraConfig[$key])) {
            return $default;
        }

        return $this->extraConfig[$key];
    }
}