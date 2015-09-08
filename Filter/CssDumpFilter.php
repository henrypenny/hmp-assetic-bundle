<?php

/*
 * This file is part of the Assetic package, an OpenSky project.
 *
 * (c) 2010-2014 OpenSky Project Inc
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hmp\AsseticBundle\Filter;

use Assetic\Asset\AssetInterface;
use Assetic\Asset\FileAsset;
use Assetic\Factory\AssetFactory;
use Assetic\Filter\BaseCssFilter;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Fixes relative CSS urls.
 *
 * @author Kris Wallsmith <kris.wallsmith@gmail.com>
 */
class CssDumpFilter extends BaseCssFilter
{
    /**
     * @var KernelInterface $kernel
     */
    private $kernel;

    /**
     * @var AssetFactory $assetFactory
     */
    private $assetFactory;

    public function __construct(KernelInterface $kernel, AssetFactory $assetFactory)
    {
        $this->kernel = $kernel;
        $this->assetFactory = $assetFactory;
    }

    public function filterLoad(AssetInterface $asset)
    {
    }

    public function filterDump(AssetInterface $asset)
    {
        $sourceBase = $asset->getSourceRoot();
        $sourcePath = $asset->getSourcePath();
        $targetPath = $asset->getTargetPath();
        $sourceDir = $asset->getSourceDirectory();

        if (null === $sourcePath || null === $targetPath || $sourcePath == $targetPath) {
            return;
        }

        // learn how to get from the target back to the source
        if (false !== strpos($sourceBase, '://')) {
            list($scheme, $url) = explode('://', $sourceBase.'/'.$sourcePath, 2);
            list($host, $path) = explode('/', $url, 2);

            $host = $scheme.'://'.$host.'/';
            $path = false === strpos($path, '/') ? '' : dirname($path);
            $path .= '/';
        } else {
            // assume source and target are on the same host
            $host = '';

            // pop entries off the target until it fits in the source
            if ('.' == dirname($sourcePath)) {
                $path = str_repeat('../', substr_count($targetPath, '/'));
            } elseif ('.' == $targetDir = dirname($targetPath)) {
                $path = dirname($sourcePath).'/';
            } else {
                $path = '';
                while (0 !== strpos($sourcePath, $targetDir)) {
                    if (false !== $pos = strrpos($targetDir, '/')) {
                        $targetDir = substr($targetDir, 0, $pos);
                        $path .= '../';
                    } else {
                        $targetDir = '';
                        $path .= '../';
                        break;
                    }
                }
                $path .= ltrim(substr(dirname($sourcePath).'/', strlen($targetDir)), '/');
            }
        }

        $content = $this->filterReferences($asset->getContent(), function ($matches) use ($sourceDir, $host) {

            $path = $sourceDir;

            if (false !== strpos($matches['url'], '://') || 0 === strpos($matches['url'], '//') || 0 === strpos($matches['url'], 'data:')) {
                // absolute or protocol-relative or data uri
                return $matches[0];
            }

            if (isset($matches['url'][0]) && '/' == $matches['url'][0]) {
                // root relative
                return str_replace($matches['url'], $host.$matches['url'], $matches[0]);
            }

            // document relative
            $url = $matches['url'];
            while (0 === strpos($url, '../') && 2 <= substr_count($path, '/')) {
                $path = substr($path, 0, strrpos(rtrim($path, '/'), '/') + 1);
                $url = substr($url, 3);
            }

            $originalAsset = $path . $url;
            $originalAsset = reset(preg_split("/[#?]/", $originalAsset));

            $rootDir = $this->kernel->getRootDir();
            $rootDir = substr($rootDir, 0, -3);

            $relativeAsset = substr($originalAsset, strlen($rootDir));

            $assetName = $this->assetFactory->generateAssetName($relativeAsset);

            $targetAssetPath = $rootDir . 'web/' . $url;

            $targetParts = explode('/', $targetAssetPath);
            $fileName = $assetName . '_' . array_pop($targetParts);
            array_push($targetParts, $fileName);
            $targetAssetPath = implode('/', $targetParts);
            $targetAssetPath = reset(preg_split("/[#?]/", $targetAssetPath));

            $urlParts = explode('/', $matches['url']);
            array_pop($urlParts);
            array_push($urlParts, $fileName);
            $urlNew = implode('/', $urlParts);

            if (!is_dir($dir = dirname($targetAssetPath))) {
                if (false === @mkdir($dir, 0777, true)) {
                    throw new \RuntimeException('Unable to create directory '.$dir);
                }
            }

            $asset = new FileAsset($originalAsset);
            try {
                $contents = $asset->dump();
            }
            catch(\Exception $e) {
                echo 'WARNING: ' . $e->getMessage();

            }
            if (false === @file_put_contents($targetAssetPath, $contents)) {
                throw new \RuntimeException('Unable to write file ' . $targetAssetPath);
            }

            $result = str_replace($matches['url'], $urlNew, $matches[0]);

            return $result;
        });

        $asset->setContent($content);
    }
}
