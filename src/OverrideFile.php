<?php

namespace ComposerOverrideFiles;

use Composer\IO\IOInterface;

class OverrideFile implements \JsonSerializable
{
    /**
     * @var string
     */
    private $rootPath;

    /**
     * @var string
     */
    private $basePath;

    /**
     * @var string
     */
    private $vendorPath;

    /**
     * @var string
     */
    private $overrideFile;

    /**
     * @var string
     */
    private $overridePath;

    /**
     * @var string
     */
    private $vendorFile;

    /**
     * @var string
     */
    private $originFile;

    /**
     * @var string
     */
    private $namespace;

    /**
     * @var string
     */
    private $className;

    /**
     * @var string|null
     */
    private $originClassName;

    /**
     * @var bool
     */
    private $generateOriginFile;
    
    public function __construct($rootPath, $basePath, $vendorPath, $overrideFile, $vendorFile, $generateOriginFile = false)
    {
        $this->rootPath = $rootPath;
        $this->basePath = $basePath;
        $this->vendorPath = $vendorPath;
        $this->overrideFile = $overrideFile;
        $this->vendorFile = $vendorFile;
        $this->generateOriginFile = $generateOriginFile;

        if ($generateOriginFile) {
            $filename = pathinfo($this->vendorFile)['basename'];
            $this->originFile = str_replace($filename, "Origin$filename", $this->vendorFile);
        }

        $this->overridePath = pathinfo($this->overrideFile)['dirname'];

        $this->namespace = $this->parseNamespace();
        $this->className = $this->parseClassName();
        $this->originClassName = $this->generateOriginFile ? "Origin{$this->getClassName()}" : null;
    }

    public function vendorFileExists()
    {
        return file_exists($this->vendorFile);
    }

    /**
     * @return string
     */
    public function getBasePath()
    {
        return $this->basePath;
    }

    /**
     * @return string
     */
    public function getVendorPath()
    {
        return $this->vendorPath;
    }

    /**
     * @return string
     */
    public function getOverrideFile($short = false)
    {
        if ($short) {
            return ltrim(str_replace($this->rootPath, '', $this->overrideFile), '/');
        }

        return $this->overrideFile;
    }

    /**
     * @return string
     */
    public function getOverridePath($short = false)
    {
        if ($short) {
            return ltrim(str_replace($this->rootPath, '', $this->overridePath), '/');
        }

        return $this->overridePath;
    }

    /**
     * @return string
     */
    public function getVendorFile($short = false)
    {
        if ($short) {
            return ltrim(str_replace($this->rootPath, '', $this->vendorFile), '/');
        }

        return $this->vendorFile;
    }

    /**
     * @param bool $short
     * @return string
     */
    public function getOriginFile($short = false)
    {
        if ($short) {
            return ltrim(str_replace($this->rootPath, '', $this->originFile), '/');
        }

        return $this->originFile;
    }

    /**
     * @return string|null
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * @return string|null
     */
    private function parseNamespace()
    {
        $content = file_get_contents($this->vendorFile);
        $re = '/namespace (.*);/m';
        preg_match($re, $content, $matches);

        if (empty($matches)) {
            return null;
        }

        return $matches[1];
    }

    /**
     * @return string|null
     */
    public function getClassName()
    {
        return $this->className;
    }

    /**
     * @return string|null
     */
    private function parseClassName()
    {
        $content = file_get_contents($this->vendorFile);
        $re = '/^((?!abstract)|final)[ ]*class ([\w]+)/m';
        preg_match($re, $content, $matches);

        if (empty($matches)) {
            return null;
        }

        return $matches[2];
    }

    /**
     * @return string
     */
    public function getOriginClassName()
    {
        return $this->originClassName;
    }

    /**
     * @param IOInterface $io
     * @return bool
     */
    public function generateOriginFile(IOInterface $io)
    {
        if (false === $this->generateOriginFile) {
            return false;
        }

        $className = $this->getClassName();
        if (empty($className)) {
            $io->writeError("<error>No className for {$this->getVendorFile(true)}.</error>.", true);

            return false;
        }


        if (copy($this->vendorFile, $this->originFile)) {
            $io->write("Generated origin file for {$this->getVendorFile(true)}.", true, IOInterface::DEBUG);
        } else {
            $io->writeError("<error>Coudn't generate origin file for {$this->getVendorFile(true)}.</error>", true);

            return false;
        }

        $content = null;
        if (!($content = file_get_contents($this->originFile))) {
            $io->writeError("<error>Coudn't open vendor file {$this->getVendorFile(true)}.</error>", true);

            return false;
        }

        $content = str_replace("class $className", "class Origin$className", $content);

        $io->write("Replaced class name $className with Origin$className for {$this->getOriginFile(true)}.", true, IOInterface::DEBUG);

        if (strpos($content, "final class Origin$className")) {
            $content = str_replace("final class Origin$className", "class Origin$className", $content);

            $io->write("Removed 'final' keyword for {$this->getOriginFile(true)}.", true, IOInterface::DEBUG);
        }

        if (file_put_contents($this->originFile, $content)) {
            $io->write("Saved changes to {$this->getOriginFile(true)}.", true, IOInterface::DEBUG);
        }

        $io->write("", true, IOInterface::DEBUG);

        return true;
    }

    public function jsonSerialize()
    {
        $reflection = new \ReflectionClass($this);

        $properties = $reflection->getProperties();

        $json = [];
        foreach ($properties as $property) {
            $json[$property->getName()] = $this->{$property->getName()};
        }

        return $json;
    }

    /**
     * @param $composer
     * @param $io
     * @param $path
     * @param $base_vendor_dir
     * @return array|OverrideFile[]
     */
    public static function getOverrideFiles($composer, $io, $path, $base_vendor_dir = null, $generateOriginFile = false)
    {
        $filesystem = new \Composer\Util\Filesystem();

        $rootPath = $filesystem->normalizePath(realpath(realpath(getcwd())));
        $basePath = $rootPath . '/' . $path;
        $vendorPath = $filesystem->normalizePath(realpath(realpath($composer->getConfig()->get('vendor-dir'))));
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($basePath));

        $files = array();
        foreach ($rii as $file) {

            if ($file->isDir()){
                continue;
            }

            $filePath = $file->getPathname();
            $relFilePath = str_replace($basePath, '', $filePath);
            $vendorFile = !empty($base_vendor_dir) ? Plugin::joinPaths($vendorPath, $base_vendor_dir, $relFilePath) : Plugin::joinPaths($vendorPath, $relFilePath);
            $overrideFile = new OverrideFile($rootPath, $basePath, $vendorPath, $filePath, $vendorFile, $generateOriginFile);
            if (false === $overrideFile->vendorFileExists()) {
                $io->writeError("<error>Can't override {$overrideFile->getVendorFile()}. File doesn't exists</error>", true);

                exit(1);
            }

            $files[] = $overrideFile;
        }

        return $files;
    }
}