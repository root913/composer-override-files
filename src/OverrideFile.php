<?php

namespace ComposerOverrideFiles;

class OverrideFile
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
    private $vendorFile;
    
    public function __construct($rootPath, $basePath, $vendorPath, $overrideFile, $vendorFile)
    {
        $this->rootPath = $rootPath;
        $this->basePath = $basePath;
        $this->vendorPath = $vendorPath;
        $this->overrideFile = $overrideFile;
        $this->vendorFile = $vendorFile;
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
            return str_replace($this->rootPath, '', $this->overrideFile);
        }

        return $this->overrideFile;
    }

    /**
     * @return string
     */
    public function getVendorFile($short = false)
    {
        if ($short) {
            return str_replace($this->rootPath, '', $this->vendorFile);
        }

        return $this->vendorFile;
    }

    /**
     * @param $composer
     * @param $io
     * @param $path
     * @param $base_vendor_dir
     * @return array|OverrideFile[]
     */
    public static function getOverrideFiles($composer, $io, $path, $base_vendor_dir = null)
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
            $overrideFile = new OverrideFile($rootPath, $basePath, $vendorPath, $filePath, $vendorFile);
            if (false === $overrideFile->vendorFileExists()) {
                $io->writeError("<error>Can't override {$overrideFile->getVendorFile()}. File doesn't exists</error>", true);
                exit(1);
            }

            $files[] = $overrideFile;
        }

        return $files;
    }
}