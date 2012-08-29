<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SwiftmailerBundle\Tests\DependencyInjection;

use Symfony\Bundle\SwiftmailerBundle\Tests\TestCase;
use Symfony\Bundle\SwiftmailerBundle\DependencyInjection\SwiftmailerExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Config\FileLocator;

class SwiftmailerExtensionTest extends TestCase
{
    public function getConfigTypes()
    {
        return array(
            array('xml'),
            array('php'),
            array('yml')
        );
    }

    /**
     * @dataProvider getConfigTypes
     */
    public function testDefaultConfig($type)
    {
        $container = $this->loadContainerFromFile('empty', $type);

        $this->assertEquals('swiftmailer.transport.smtp', (string) $container->getAlias('swiftmailer.transport'));
    }

    /**
     * @dataProvider getConfigTypes
     */
    public function testNullTransport($type)
    {
        $container = $this->loadContainerFromFile('null', $type);

        $this->assertEquals('swiftmailer.transport.null', (string) $container->getAlias('swiftmailer.transport'));
    }

    /**
     * @dataProvider getConfigTypes
     */
    public function testFull($type)
    {
        $container = $this->loadContainerFromFile('full', $type);


        $this->assertEquals('swiftmailer.transport.spool', (string) $container->getAlias('swiftmailer.transport'));
        $this->assertEquals('swiftmailer.transport.smtp', (string) $container->getAlias('swiftmailer.transport.real'));
        $this->assertTrue($container->has('swiftmailer.spool.memory'));
        $this->assertEquals('example.org', $container->getParameter('swiftmailer.transport.smtp.host'));
        $this->assertEquals('12345', $container->getParameter('swiftmailer.transport.smtp.port'));
        $this->assertEquals('tls', $container->getParameter('swiftmailer.transport.smtp.encryption'));
        $this->assertEquals('user', $container->getParameter('swiftmailer.transport.smtp.username'));
        $this->assertEquals('pass', $container->getParameter('swiftmailer.transport.smtp.password'));
        $this->assertEquals('login', $container->getParameter('swiftmailer.transport.smtp.auth_mode'));
        $this->assertEquals('1000', $container->getParameter('swiftmailer.transport.smtp.timeout'));
        $this->assertEquals('127.0.0.1', $container->getParameter('swiftmailer.transport.smtp.source_ip'));
        $this->assertSame(array('swiftmailer.plugin' => array(array())), $container->getDefinition('swiftmailer.plugin.redirecting')->getTags());
        $this->assertSame('single@host.com', $container->getParameter('swiftmailer.single_address'));
        $this->assertEquals(array('/foo@.*/', '/.*@bar.com$/'), $container->getParameter('swiftmailer.delivery_whitelist'));
    }

    /**
     * @dataProvider getConfigTypes
     */
    public function testSpool($type)
    {
        $container = $this->loadContainerFromFile('spool', $type);

        $this->assertEquals('swiftmailer.transport.spool', (string) $container->getAlias('swiftmailer.transport'));
        $this->assertEquals('swiftmailer.transport.smtp', (string) $container->getAlias('swiftmailer.transport.real'));
        $this->assertTrue($container->has('swiftmailer.spool.file'), 'Default is file based spool');
    }

    /**
     * @dataProvider getConfigTypes
     */
    public function testMemorySpool($type)
    {
        $container = $this->loadContainerFromFile('spool_memory', $type);

        $this->assertEquals('swiftmailer.transport.spool', (string) $container->getAlias('swiftmailer.transport'));
        $this->assertEquals('swiftmailer.transport.smtp', (string) $container->getAlias('swiftmailer.transport.real'));
        $this->assertTrue($container->has('swiftmailer.spool.memory'), 'Memory based spool is configured');
    }

    /**
     * @dataProvider getConfigTypes
     */
    public function testSmtpConfig($type)
    {
        $container = $this->loadContainerFromFile('smtp', $type);

        $this->assertEquals('swiftmailer.transport.smtp', (string) $container->getAlias('swiftmailer.transport'));

        $this->assertEquals('example.org', $container->getParameter('swiftmailer.transport.smtp.host'));
        $this->assertEquals('12345', $container->getParameter('swiftmailer.transport.smtp.port'));
        $this->assertEquals('tls', $container->getParameter('swiftmailer.transport.smtp.encryption'));
        $this->assertEquals('user', $container->getParameter('swiftmailer.transport.smtp.username'));
        $this->assertEquals('pass', $container->getParameter('swiftmailer.transport.smtp.password'));
        $this->assertEquals('login', $container->getParameter('swiftmailer.transport.smtp.auth_mode'));
        $this->assertEquals('1000', $container->getParameter('swiftmailer.transport.smtp.timeout'));
        $this->assertEquals('127.0.0.1', $container->getParameter('swiftmailer.transport.smtp.source_ip'));
    }

    /**
     * @dataProvider getConfigTypes
     */
    public function testRedirectionConfig($type)
    {
        $container = $this->loadContainerFromFile('redirect', $type);

        $this->assertSame(array('swiftmailer.plugin' => array(array())), $container->getDefinition('swiftmailer.plugin.redirecting')->getTags());
        $this->assertSame('single@host.com', $container->getParameter('swiftmailer.single_address'));
        $this->assertEquals(array('/foo@.*/', '/.*@bar.com$/'), $container->getParameter('swiftmailer.delivery_whitelist'));
    }

    /**
     * @dataProvider getConfigTypes
     */
    public function testSingleRedirectionConfig($type)
    {
        $container = $this->loadContainerFromFile('redirect_single', $type);

        $this->assertSame(array('swiftmailer.plugin' => array(array())), $container->getDefinition('swiftmailer.plugin.redirecting')->getTags());
        $this->assertSame('single@host.com', $container->getParameter('swiftmailer.single_address'));
        $this->assertEquals(array('/foo@.*/'), $container->getParameter('swiftmailer.delivery_whitelist'));
    }

    /**
     * @dataProvider getConfigTypes
     */
    public function testAntifloodConfig($type)
    {
        $container = $this->loadContainerFromFile('antiflood', $type);

        $this->assertSame(array('swiftmailer.plugin' => array(array())), $container->getDefinition('swiftmailer.plugin.antiflood')->getTags());
    }

    /**
     * @param string $file
     * @param string $type
     * @return ContainerBuilder
     */
    private function loadContainerFromFile($file, $type)
    {
        $container = new ContainerBuilder();

        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.cache_dir', '/tmp');

        $container->registerExtension(new SwiftmailerExtension());
        $locator = new FileLocator(__DIR__ . '/Fixtures/config/' . $type);

        switch ($type) {
            case 'xml':
                $loader = new XmlFileLoader($container, $locator);
                break;

            case 'yml':
                $loader = new YamlFileLoader($container, $locator);
                break;

            case 'php':
                $loader = new PhpFileLoader($container, $locator);
                break;
        }

        $loader->load($file . '.' . $type);

        $container->getCompilerPassConfig()->setOptimizationPasses(array());
        $container->getCompilerPassConfig()->setRemovingPasses(array());
        $container->compile();

        return $container;
    }
}
