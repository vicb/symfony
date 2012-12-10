<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ClassLoader\Tests;

use Symfony\Component\ClassLoader\ClassMapGenerator;

class ClassMapGeneratorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var string $workspace
     */
    private $workspace = null;

    public function prepare_workspace()
    {
        $this->workspace = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.time().rand(0, 1000);
        mkdir($this->workspace, 0777, true);
        $this->workspace = realpath($this->workspace);
    }

    /**
     * @param string $file
     */
    private function clean($file)
    {
        if (is_dir($file) && !is_link($file)) {
            $dir = new \FilesystemIterator($file);
            foreach ($dir as $childFile) {
                $this->clean($childFile);
            }

            rmdir($file);
        } else {
            unlink($file);
        }
    }

    /**
     * @dataProvider getTestCreateMapTests
     */
    public function testDump($directory, $expected)
    {
        $this->prepare_workspace();

        $file = $this->workspace.'/file';

        $generator = new ClassMapGenerator();
        $generator->dump($directory, $file);
        $this->assertFileExists($file);

        $this->clean($this->workspace);
    }

    /**
     * @dataProvider getTestCreateMapTests
     */
    public function testCreateMap($directory, $expected)
    {
        $this->assertEqualsNormalized($expected, ClassMapGenerator::createMap($directory));
    }

    public function getTestCreateMapTests()
    {
        $data = array(
            array(__DIR__.'/Fixtures/Namespaced', array(
                'Namespaced\\WithComments' => realpath(__DIR__).'/Fixtures/Namespaced/WithComments.php',
                'Namespaced\\Bar'          => realpath(__DIR__).'/Fixtures/Namespaced/Bar.php',
                'Namespaced\\Foo'          => realpath(__DIR__).'/Fixtures/Namespaced/Foo.php',
                'Namespaced\\Baz'          => realpath(__DIR__).'/Fixtures/Namespaced/Baz.php',
                )
            ),
            array(__DIR__.'/Fixtures/beta/NamespaceCollision', array(
                'NamespaceCollision\\C\\B\\Bar' => realpath(__DIR__).'/Fixtures/beta/NamespaceCollision/C/B/Bar.php',
                'NamespaceCollision\\C\\B\\Foo' => realpath(__DIR__).'/Fixtures/beta/NamespaceCollision/C/B/Foo.php',
                'NamespaceCollision\\A\\B\\Bar' => realpath(__DIR__).'/Fixtures/beta/NamespaceCollision/A/B/Bar.php',
                'NamespaceCollision\\A\\B\\Foo' => realpath(__DIR__).'/Fixtures/beta/NamespaceCollision/A/B/Foo.php',
            )),
            array(__DIR__.'/Fixtures/Pearlike', array(
                'Pearlike_WithComments' => realpath(__DIR__).'/Fixtures/Pearlike/WithComments.php',
                'Pearlike_Bar'          => realpath(__DIR__).'/Fixtures/Pearlike/Bar.php',
                'Pearlike_Foo'          => realpath(__DIR__).'/Fixtures/Pearlike/Foo.php',
                'Pearlike_Baz'          => realpath(__DIR__).'/Fixtures/Pearlike/Baz.php',
            )),
            array(__DIR__.'/Fixtures/classmap', array(
                'ClassMap\\SomeClass'     => realpath(__DIR__).'/Fixtures/classmap/SomeClass.php',
                'ClassMap\\SomeParent'    => realpath(__DIR__).'/Fixtures/classmap/SomeParent.php',
                'ClassMap\\SomeInterface' => realpath(__DIR__).'/Fixtures/classmap/SomeInterface.php',
                'A'                       => realpath(__DIR__).'/Fixtures/classmap/multipleNs.php',
                'Alpha\\A'                => realpath(__DIR__).'/Fixtures/classmap/multipleNs.php',
                'Alpha\\B'                => realpath(__DIR__).'/Fixtures/classmap/multipleNs.php',
                'Beta\\A'                 => realpath(__DIR__).'/Fixtures/classmap/multipleNs.php',
                'Beta\\B'                 => realpath(__DIR__).'/Fixtures/classmap/multipleNs.php',
                'Foo\\Bar\\A'             => realpath(__DIR__).'/Fixtures/classmap/sameNsMultipleClasses.php',
                'Foo\\Bar\\B'             => realpath(__DIR__).'/Fixtures/classmap/sameNsMultipleClasses.php',
            )),
        );

        if (version_compare(PHP_VERSION, '5.4', '>=')) {
            $data[] = array(__DIR__.'/Fixtures/php5.4/traits', array(
                'TFoo' => __DIR__.'/Fixtures/php5.4/traits/traits.php',
                'CFoo' => __DIR__.'/Fixtures/php5.4/traits/traits.php',
                'Foo\\TBar' => __DIR__.'/Fixtures/php5.4/traits/traits.php',
                'Foo\\IBar' => __DIR__.'/Fixtures/php5.4/traits/traits.php',
                'Foo\\TFooBar' => __DIR__.'/Fixtures/php5.4/traits/traits.php',
                'Foo\\CBar' => __DIR__.'/Fixtures/php5.4/traits/traits.php',
            ));

            $data[] = array(__DIR__.'/Fixtures/php5.4/nested_traits', array(
                'TFooBarBase' => __DIR__.'/Fixtures/php5.4/nested_traits/nested_traits.php',
                'TFooBar' => __DIR__.'/Fixtures/php5.4/nested_traits/nested_traits.php',
                'TFooBar2' => __DIR__.'/Fixtures/php5.4/nested_traits/nested_traits.php',
                'CFooBar' => __DIR__.'/Fixtures/php5.4/nested_traits/nested_traits.php',
            ));
        }

        return $data;
    }

    public function testCreateMapFinderSupport()
    {
        if (!class_exists('Symfony\\Component\\Finder\\Finder')) {
            $this->markTestSkipped('Finder component is not available');
        }

        $finder = new \Symfony\Component\Finder\Finder();
        $finder->files()->in(__DIR__ . '/Fixtures/beta/NamespaceCollision');

        $this->assertEqualsNormalized(array(
            'NamespaceCollision\\C\\B\\Bar' => realpath(__DIR__).'/Fixtures/beta/NamespaceCollision/C/B/Bar.php',
            'NamespaceCollision\\C\\B\\Foo' => realpath(__DIR__).'/Fixtures/beta/NamespaceCollision/C/B/Foo.php',
            'NamespaceCollision\\A\\B\\Bar' => realpath(__DIR__).'/Fixtures/beta/NamespaceCollision/A/B/Bar.php',
            'NamespaceCollision\\A\\B\\Foo' => realpath(__DIR__).'/Fixtures/beta/NamespaceCollision/A/B/Foo.php',
        ), ClassMapGenerator::createMap($finder));
    }

    protected function assertEqualsNormalized(array $expected, array $actual, $message = null)
    {
        foreach ($expected as $ns => $path) {
            $expected[$ns] = strtr($path, '\\', '/');
        }
        foreach ($actual as $ns => $path) {
            $actual[$ns] = strtr($path, '\\', '/');
        }
        $this->assertEquals($expected, $actual, $message);
        $this->assertEquals(array_keys($expected), array_keys($actual), 'Wrong order - ' + $message);
    }
}
