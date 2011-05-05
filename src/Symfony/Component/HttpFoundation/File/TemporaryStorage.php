<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\File;

use Symfony\Component\HttpFoundation\File\Exception\DirectoryCreationException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\Exception\UnexpectedTypeException;

/**
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class TemporaryStorage
{
    private $directory;
    private $secret;
    private $size;
    private $ttlSec;

    /**
     * Constructor.
     *
     * @param string   $secret      A secret
     * @param sting    $directory   The base directory
     * @param integer  $size        The maximum size for the temporary storage (in Bytes)
     *                              Should be set to 0 for an unlimited size.
     * @param integer  $ttlSec      The time to live in seconds (a positive number)
     *                              Should be set to 0 for an infinite ttl
     *
     * @throws DirectoryCreationException if the directory does not exist or fails to be created
     */
    public function __construct($secret, $directory, $size = 0, $ttlSec = 0)
    {
        if (!is_dir($directory)) {
            if (file_exists($directory) || false === mkdir($directory, 0777, true)) {
                throw new DirectoryCreationException(($directory));
            }
        }

        $this->directory = realpath($directory);
        $this->secret = $secret;
        $this->size = max((int) $size, 0);
        $this->ttlSec = max((int) $ttlSec, 0);
    }

    /**
     * Move an existing file to the temporary storage
     *
     * @param File|string $file An exisiting file
     *
     * @return string The token to used to retrieve the file
     *
     * @throws UnexpectedTypeException if the file is not a filename or an instance of File
     * @throws FileNotFoundException if the file does not exists
     * @throws DirectotyCreationException if the target folder could not be created
     */
    public function add($file)
    {
        $originalFile = $file;

        if (is_string($file)) {
            $file = new File($file);
        }

        if (!$file instanceof File) {
            throw new UnexpectedTypeException($originalFile, 'string or Symfony\Component\HttpFoundation\File\File');
        }

        // Prevent the temporary storage from getting flooded
        $this->removeExpiredFiles();

        $token = sprintf("%f%d", microtime(true), rand(100000, 999999));
        $target = $this->get($token);

        $directory = dirname($target);
        if (!is_dir($directory)) {
            if (file_exists($directory) || false === mkdir($directory, 0777, true)) {
                throw new DirectoryCreationException($directory);
            }
        }
        $file->move($directory, basename($target));

        return $token;
    }

    /**
     * Return the path to file in the temporary storage.
     *
     * @param string $token The token returned while adding the file to the storage
     *
     * @return string The file name
     */
    public function get($token)
    {
        if (!is_string($token)) {
            throw new UnexpectedTypeException($token, 'string');
        }

        $hash = $this->generateHash($token);

        $segments = array($this->directory, substr($hash, 0, 2), substr($hash, 2, 2), substr($hash, 4));

        return implode(DIRECTORY_SEPARATOR, $segments);
    }

    /**
     * Truncates the temporary storage folder to its maximum size.
     *
     * @return Boolean true when some files had to be deleted
     *
     * @throws FileException if a problem occurs while deleting a file
     */
    public function removeExpiredFiles()
    {
        $truncated = false;

        if (0 == $this->size && 0 == $this->ttlSec) {
            return false;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory,\RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $files = array();
        $size = 0;
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
                $files[$file->getRealPath()] = array(
                  'size'  => $file->getSize(),
                  'mtime' => $file->getMTime(),
                );
            }
        }

        if ($this->ttlSec > 0) {
            $keepAfter = time() - $this->ttlSec;
            foreach ($files as $path => $file) {
                if ($file['mtime'] < $keepAfter) {
                    $truncated = true;
                    if (false === @unlink($path)) {
                        throw new FileException(sprintf('Unable to delete the file "%s"', $path));
                    }
                    $size -= $file['size'];
                }
            }
        }

        if ($this->size > 0) {
            uasort($files, function($f1, $f2) { return $f1['mtime'] > $f2['mtime']; });
            $file = reset($files);
            while ($size > $this->size) {
                $truncated = true;
                $path = key($files);
                if (false === @unlink($path)) {
                    throw new FileException(sprintf('Unable to delete the file "%s"', $path));
                }
                $size -= $file['size'];
                $file = next($files);
            }
        }

        return $truncated;
    }

    protected function generateHashInfo($token)
    {
        return $this->secret.$token;
    }

    protected function generateHash($token)
    {
        return md5($this->generateHashInfo($token));
    }
}
