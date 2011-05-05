<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Extension\Core\Type;

use Symfony\Component\Form\FileField;
use Symfony\Component\HttpFoundation\File\File;

class FileTypeTest extends TypeTestCase
{
    public static $tmpFiles = array();

    protected static $tmpDir;

    protected $form;

    public static function setUpBeforeClass()
    {
        self::$tmpDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'symfony-test';

        if (!file_exists(self::$tmpDir)) {
            mkdir(self::$tmpDir, 0777, true);
        }
    }

    protected function setUp()
    {
        parent::setUp();

        $this->form = $this->factory->create('file');
    }

    protected function tearDown()
    {
        foreach (self::$tmpFiles as $key => $file) {
            @unlink($file);
            unset(self::$tmpFiles[$key]);
        }
    }

    public function createTmpFile($path)
    {
        self::$tmpFiles[] = $path;
        file_put_contents($path, 'foobar');
    }

    public function testSubmitUploadsNewFiles()
    {
        $tmpDir = self::$tmpDir;
        $generatedToken = '';

        $this->storage
            ->expects($this->once())
            ->method('add')
            ->will($this->returnValue('token'))
        ;

        $file = $this
            ->getMockBuilder('Symfony\Component\HttpFoundation\File\UploadedFile')
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $file
             ->expects($this->once())
             ->method('isValid')
             ->will($this->returnValue(true))
        ;
        $file
             ->expects($this->once())
             ->method('getOriginalName')
             ->will($this->returnValue('original_name.jpg'))
        ;
        $file
             ->expects($this->any())
             ->method('getPath')
             ->will($this->returnValue($tmpDir.'/original_name.jpg'))
        ;

        $this->form->bind(array(
            'file'  => $file,
            'token' => '',
            'name'  => '',
        ));

        $this->assertEquals(array(
                'file'  => $file,
                'token' => 'token',
                'name'  => 'original_name.jpg',
            ),
            $this->form->getClientData()
        );

        $this->assertEquals($tmpDir.'/original_name.jpg', $this->form->getData());
    }

    public function testSubmitKeepsUploadedFilesOnErrors()
    {
        $tmpDir = self::$tmpDir;
        $tmpPath = $tmpDir . DIRECTORY_SEPARATOR . 'original_name.jpg';
        $this->createTmpFile($tmpPath);

        $this->storage
            ->expects($this->once())
            ->method('get')
            ->with('123456')
            ->will($this->returnValue($tmpPath));

        $this->form->bind(array(
            'file'  => null,
            'token' => '123456',
            'name'  => 'original_name.jpg',
        ));

        $data = $this->form->getClientData();
        $file = $data['file'];

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\File\UploadedFile', $file);
        $this->assertEquals('original_name.jpg', $file->getOriginalName());
        $this->assertEquals(realPath($tmpPath), realpath($file->getPath()));
        $this->assertEquals('123456', $data['token']);
        $this->assertEquals('original_name.jpg', $data['name']);
    }

    public function testSubmitEmpty()
    {
        $this->storage
            ->expects($this->never())
            ->method('getTempDir')
        ;

        $this->form->bind(array(
            'file'  => '',
            'token' => '',
            'name'  => '',
        ));

        $this->assertEquals(array(
                'file'  => '',
                'token' => '',
                'name'  => '',
            ),
            $this->form->getClientData()
        );

        $this->assertEquals(null, $this->form->getData());
    }

    public function testSubmitEmptyKeepsExistingFiles()
    {
        $tmpPath = self::$tmpDir . DIRECTORY_SEPARATOR . 'original_name.jpg';
        $this->createTmpFile($tmpPath);
        $file = new File($tmpPath);

        $this->storage
            ->expects($this->never())
            ->method('getTempDir')
        ;

        $this->form->setData($tmpPath);
        $this->form->bind(array(
            'file'  => '',
            'token' => '',
            'name'  => '',
        ));

        $this->assertEquals(array(
                'file'  => $file,
                'token' => '',
                'name'  => '',
            ),
            $this->form->getClientData()
        );
        $this->assertEquals(realpath($tmpPath), realpath($this->form->getData()));
    }
}
