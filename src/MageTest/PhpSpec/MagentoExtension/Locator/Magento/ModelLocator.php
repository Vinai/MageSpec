<?php
/**
 * MageSpec
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License, that is bundled with this
 * package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 *
 * http://opensource.org/licenses/MIT
 *
 * If you did not receive a copy of the license and are unable to obtain it
 * through the world-wide-web, please send an email
 * to <magetest@sessiondigital.com> so we can send you a copy immediately.
 *
 * @category   MageTest
 * @package    PhpSpec_MagentoExtension
 *
 * @copyright  Copyright (c) 2012-2013 MageTest team and contributors.
 */
namespace MageTest\PhpSpec\MagentoExtension\Locator\Magento;

use InvalidArgumentException;
use PhpSpec\Locator\ResourceLocatorInterface;
use PhpSpec\Util\Filesystem;

/**
 * ModelLocator
 *
 * @category   MageTest
 * @package    PhpSpec_MagentoExtension
 *
 * @author     MageTest team (https://github.com/MageTest/MageSpec/contributors)
 */
class ModelLocator implements ResourceLocatorInterface
{
    const LOCAL_CODE_POOL = 'local';
    const COMMUNITY_CODE_POOL = 'community';

    const MODEL_FOLDER = 'Model';

    private $srcPath;
    private $specPath;
    private $srcNamespace;
    private $specNamespace;
    private $fullSrcPath;
    private $fullSpecPath;
    private $filesystem;

    public function __construct($srcNamespace = '', $specNamespacePrefix = '',
                                $srcPath = 'src', $specPath = 'spec', Filesystem $filesystem = null)
    {
        $this->filesystem = $filesystem ? : new Filesystem;

        $this->srcPath       = rtrim(realpath($srcPath), '/\\') . DIRECTORY_SEPARATOR . self::LOCAL_CODE_POOL . DIRECTORY_SEPARATOR;
        $this->specPath      = rtrim(realpath($specPath), '/\\') . DIRECTORY_SEPARATOR . self::LOCAL_CODE_POOL . DIRECTORY_SEPARATOR;
        $this->srcNamespace  = ltrim(trim($srcNamespace, ' \\') . '\\', '\\');
        $this->specNamespace = trim($specNamespacePrefix, ' \\') . '\\';
        $this->fullSrcPath   = $this->srcPath;
        $this->fullSpecPath  = $this->specPath;

        if (DIRECTORY_SEPARATOR === $this->srcPath) {
            throw new InvalidArgumentException(sprintf(
                'Source code path should be existing filesystem path, but "%s" given.',
                $srcPath
            ));
        }

        if (DIRECTORY_SEPARATOR === $this->specPath) {
            throw new InvalidArgumentException(sprintf(
                'Specs code path should be existing filesystem path, but "%s" given.',
                $specPath
            ));
        }
    }

    public function getFullSrcPath()
    {
        return $this->fullSrcPath;
    }

    public function getFullSpecPath()
    {
        return $this->fullSpecPath;
    }

    public function getSrcNamespace()
    {
        return $this->srcNamespace;
    }

    public function getSpecNamespace()
    {
        return $this->specNamespace;
    }

    public function getAllResources()
    {
        return $this->findSpecResources($this->fullSpecPath);
    }

    public function supportsQuery($query)
    {
        $validator   = '/^(model):([A-Za-z0-9]+)_([A-Za-z0-9]+)\/([A-Za-z0-9]+)(_[\w]+)?$/';
        $isSupported = (bool) preg_match($validator, $query);

        return $isSupported;
    }

    public function findResources($query)
    {
        $path = rtrim(realpath(str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $query)), DIRECTORY_SEPARATOR);

        if ('.php' !== substr($path, -4)) {
            $path .= DIRECTORY_SEPARATOR;
        }

        if ($path && 0 === strpos($path, $this->fullSrcPath)) {
            $path = $this->fullSpecPath.substr($path, strlen($this->fullSrcPath));
            $path = preg_replace('/\.php/', 'Spec.php', $path);

            return $this->findSpecResources($path);
        }

        if ($path && 0 === strpos($path, $this->srcPath)) {
            $path = $this->fullSpecPath.substr($path, strlen($this->srcPath));
            $path = preg_replace('/\.php/', 'Spec.php', $path);

            return $this->findSpecResources($path);
        }

        if ($path && 0 === strpos($path, $this->specPath)) {
            return $this->findSpecResources($path);
        }

        return array();
    }

    public function supportsClass($classname)
    {
        $parts = explode('_', $classname);
        $isSupported = !empty($parts);

        return $isSupported;
    }

    public function createResource($classname)
    {
        $validator = '/^(model):([A-Za-z0-9]+)_([A-Za-z0-9]+)\/([A-Za-z0-9]+)(_[\w]+)?$/';
        preg_match($validator, $classname, $matches);

        if (!empty($matches)) {
            array_shift($matches);
            array_shift($matches);

            $vendor = array_shift($matches);
            $module = array_shift($matches);
            $model  = implode($matches);

            $classname = implode('_', array($vendor, $module, self::MODEL_FOLDER, $model));
        }

        return new ModelResource(explode('_', $classname), $this);
    }

    public function getPriority()
    {
        return 42;
    }

    protected function findSpecResources($path)
    {
        if (!$this->filesystem->pathExists($path)) {
            return array();
        }

        if ('.php' === substr($path, -4)) {
            return array($this->createResourceFromSpecFile(realpath($path)));
        }

        $resources = array();
        foreach ($this->filesystem->findPhpFilesIn($path) as $file) {
            $resources[] = $this->createResourceFromSpecFile($file->getRealPath());
        }

        return $resources;
    }

    private function createResourceFromSpecFile($path)
    {
        // cut "Spec.php" from the end
        $relative = substr($path, strlen($this->fullSpecPath), -4);
        $relative = preg_replace('/Spec$/', '', $relative);

        return new ModelResource(explode(DIRECTORY_SEPARATOR, $relative), $this);
    }
}