<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpFoundation\File;

use Symfony\Component\HttpFoundation\File\TemporaryStorage;

class TemporaryStorageTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException Symfony\Component\HttpFoundation\File\Exception\DirectoryCreationException
     */
    public function testThrowsAnExceptionWhenUnableToCreateTheDirectory()
    {
        $storage = new TemporaryStorage('secret', __DIR__.'/Fixtures/test.gif');
    }

    /**
     * @expectedException Symfony\Component\HttpFoundation\File\Exception\UnexpectedTypeException
     */
    public function testAddThrowsAnExceptionIfFileIsNotAFileOrAFilename()
    {
        $storage = new TemporaryStorage('secret', __DIR__.'/Fixtures/storage');
        $storage->add(array());
    }

    /**
     * @expectedException Symfony\Component\HttpFoundation\File\Exception\FileException
     */
    public function testAddThrowsAnExceptionIfTheFileDoesNotExist()
    {
        $storage = new TemporaryStorage('secret', __DIR__.'/Fixtures/storage');
        $storage->add('/not/a/file');
    }

    /**
     * @expectedException Symfony\Component\HttpFoundation\File\Exception\UnexpectedTypeException
     */
    public function testGetThrowsAnExceptionIfTokenIsNotAString()
    {
        $storage = new TemporaryStorage('secret', __DIR__.'/Fixtures/storage');
        $storage->get(array());
    }

    public function testAddAFileToTheStorage()
    {

        $srcFile = __DIR__.'/Fixtures/foo';
        $storagePath = __DIR__.'/Fixtures/storage';
        $targetFile = $storagePath.'/aa/bb/foo';

        $source = touch($srcFile);

        $storage = $this
            ->getMockBuilder('Symfony\Component\HttpFoundation\File\TemporaryStorage')
            ->setConstructorArgs(array('secret', $storagePath))
            ->setMethods(array('generateHash'))
            ->getMock()
        ;

        $storage
            ->expects($this->exactly(2))
            ->method('generateHash')
            ->will($this->returnValue('aabbfoo'))
        ;

        $token = $storage->add($srcFile);

        $this->assertFalse(file_exists($srcFile));
        $this->assertTrue(file_exists($targetFile));
        $this->assertEquals(realpath($targetFile), realpath($storage->get($token)));

        unlink($targetFile);
        rmdir(dirname($targetFile));
        rmdir(dirname(dirname($targetFile)));
    }

    public function testDoNotTruncateWhenSizeIsZero()
    {
        $file = __DIR__.'/Fixtures/foo';
        touch($file);

        $storage = new TemporaryStorage('secret', __DIR__.'/Fixtures/storage');
        $token = $storage->add($file);

        $this->assertFalse($storage->removeExpiredFiles());
        $this->assertTrue(file_exists($storage->get($token)));

        unlink($storage->get($token));
    }

    public function testRemoveFilesWhenCapacityIsExceeded()
    {
        $path = __DIR__.'/Fixtures/storage';

        $files = array(
            $path.'/sub1/foo_1',
            $path.'/sub1/foo_2',
            $path.'/sub2/foo_3',
            $path.'/sub2/foo_4',
        );

        foreach ($files as $i => $file) {
            $size = file_put_contents($file, "foobar");
            if ($i % 2) {
                touch($file, time() - 500);
            }
        }

        $storage = new TemporaryStorage('secret', $path, 2 * $size);

        $this->assertTrue($storage->removeExpiredFiles());

        foreach ($files as $i => $file) {
            if ($i % 2) {
                $this->assertFalse(file_exists($file));
            } else {
                $this->assertTrue(file_exists($file));
                unlink($file);
            }
        }
    }

    public function testDoNotRemoveWhenCapacityIsNotExceeded()
    {
        $path = __DIR__.'/Fixtures/storage';

        $files = array(
            $path.'/sub1/foo_1',
            $path.'/sub1/foo_2',
            $path.'/sub2/foo_3',
            $path.'/sub2/foo_4',
        );

        foreach ($files as $file) {
            $size = file_put_contents($file, "foobar");
        }

        $storage = new TemporaryStorage('secret', $path, 4 * $size);

        $this->assertFalse($storage->removeExpiredFiles());

        foreach ($files as $file) {
            $this->assertTrue(file_exists($file));
            unlink($file);
        }
    }

    public function testRemoveOutdatedFiles()
    {
        $path = __DIR__.'/Fixtures/storage';

        $files = array(
            $path.'/sub1/foo_1',
            $path.'/sub1/foo_2',
            $path.'/sub2/foo_3',
            $path.'/sub2/foo_4',
        );

        foreach ($files as $i => $file) {
            $size = file_put_contents($file, "foobar");
            if ($i % 2) {
                touch($file, time() - 35 * 60);
            }
        }

        $storage = new TemporaryStorage('secret', $path, 0, 30 * 60);

        $this->assertTrue($storage->removeExpiredFiles());

        foreach ($files as $i => $file) {
            if ($i % 2) {
                $this->assertFalse(file_exists($file));
            } else {
                $this->assertTrue(file_exists($file));
                unlink($file);
            }
        }
    }
}