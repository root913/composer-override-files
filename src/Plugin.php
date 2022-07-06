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
        if (empty($this->extraConfig)) {
            return;
        }

        $this->validate();

        $generateOriginFile = $this->getConfig('generate_origin_file', false);
        $overrideFiles = OverrideFile::getOverrideFiles($this->composer, $this->io, $this->getConfig('path'), $this->getConfig('base_vendor_dir'), $generateOriginFile);

        if ($generateOriginFile) {
            $autoloads = $this->overrideFilesWithOrigin($overrideFiles, $this->composer->getPackage()->getAutoload());
        } else {
            $autoloads = $this->overrideFiles($overrideFiles, $this->composer->getPackage()->getAutoload());
        }

        if ($this->io->isVerbose()) {
            $this->io->write("", true, IOInterface::VERBOSE);
            foreach ($overrideFiles as $overrideFile) {
                $this->io->write("\033[32m> " . $overrideFile->getVendorFile(true) . "\033[0m", true, IOInterface::VERBOSE);
                $this->io->write("\033[33m" . json_encode($overrideFile, JSON_PRETTY_PRINT) . "\033[0m", true, IOInterface::VERBOSE);
            }

            $this->io->write("", true, IOInterface::VERBOSE);
            $this->io->write("\033[32m> Autoload" . "\033[0m", true, IOInterface::VERY_VERBOSE);
            $this->io->write("\033[33m" . json_encode(['autoload' => $autoloads], JSON_PRETTY_PRINT) . "\033[0m", true, IOInterface::VERY_VERBOSE);
        }

        $this->composer->getPackage()->setAutoload($autoloads);
    }

    /**
     * @param array|OverrideFile[] $files
     * @param array $autoloads
     * @return array
     */
    private function overrideFiles(array $files, array $autoloads)
    {
        $psr4 = [];

        foreach ($files as $overrideFile) {
            $excludeFromClassmap = $overrideFile->getVendorFile(true);
            $ps4Path = $overrideFile->getOverridePath(true);

            if (!empty($excludeFromClassmap) && !empty($ps4Path)) {
                $psr4["{$overrideFile->getNamespace()}\\"] = $ps4Path;
                $autoloads['exclude-from-classmap'][] = $excludeFromClassmap;
            } else {
                $this->io->write("\033[33mCoudn't generate override autoload for {$overrideFile->getVendorFile(true)}" . "\033[0m", true);

                continue;
            }

            $this->io->write("Overrided file: <info>{$overrideFile->getVendorFile(true)}</info> => <info>{$overrideFile->getOverrideFile(true)}</info>");
        }

        $autoloads['psr-4'] = array_unique($psr4);

        return $autoloads;
    }

    /**
     * @param array|OverrideFile[] $files
     * @param array $autoloads
     * @return array
     */
    private function overrideFilesWithOrigin(array $files, array $autoloads)
    {
        $psr4 = [];

        foreach ($files as $overrideFile) {
            $originCreated = $overrideFile->generateOriginFile($this->io);
            if (false === $originCreated) {
                 $this->io->writeError("<error>Origin file for class {$overrideFile->getVendorFile(true)} couldn't be created. Only classes allowed without abstract keyword.</error>", true);
                 exit(1);
            }

            $excludeFromClassmap = $overrideFile->getVendorFile(true);
            $file = $overrideFile->getOriginFile(true);
            $ps4Path = $overrideFile->getOverridePath(true);
            if (!empty($excludeFromClassmap) && !empty($file) && !empty($ps4Path)) {
                $psr4["{$overrideFile->getNamespace()}\\"] = $ps4Path;
                $autoloads['exclude-from-classmap'][] = $excludeFromClassmap;
                $autoloads['files'][] = $file;
            } else {
                $this->io->write("\033[33mCoudn't generate override autoload for {$overrideFile->getVendorFile(true)}" . "\033[0m", true);

                continue;
            }

            $namespace = "{$overrideFile->getNamespace()}\\{$overrideFile->getOriginClassName()}";

            $this->io->write("Overrided file: <info>{$overrideFile->getVendorFile(true)}</info> => <info>{$overrideFile->getOverrideFile(true)}</info>. Origin file can be accessed by <info>$namespace</info> namespace");
        }

        if (!empty($psr4)) {
            $autoloads['psr-4'] = array_unique($psr4);
        }

        return $autoloads;
    }

    private function validate()
    {
        $filesystem = new \Composer\Util\Filesystem();
        $rootPath = $filesystem->normalizePath(realpath(realpath(getcwd())));
        $vendorPath = $filesystem->normalizePath(realpath(realpath($this->composer->getConfig()->get('vendor-dir'))));

        $path = self::joinPaths($rootPath, $this->getConfig('path'));
        $baseVendorDir = self::joinPaths($vendorPath, $this->getConfig('base_vendor_dir'));
        $generateOriginFile = $this->getConfig('generate_origin_file', false);

        if (empty($path)) {
            $this->io->writeError("<error>Override file directory cannot be empty</error>", true);
            exit(1);
        }

        if (false === file_exists($path)) {
            $this->io->writeError("<error>Override file directory $path (override_files.path) doesn't exists</error>", true);
            exit(1);
        }

        if (!empty($baseVendorDir)) {
            if (false === file_exists($baseVendorDir)) {
                $this->io->writeError("<error>Override file base directory $baseVendorDir (override_files.base_vendor_dir) doesn't exists</error>", true);
                exit(1);
            }
        }

        if (false === is_bool($generateOriginFile)) {
            $this->io->writeError("<error>Generate origin file flag (override_files.generate_origin_file) must be of boolean type</error>", true);
            exit(1);
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