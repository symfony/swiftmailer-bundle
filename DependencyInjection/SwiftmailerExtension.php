<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SwiftmailerBundle\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;

/**
 * SwiftmailerExtension is an extension for the SwiftMailer library.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SwiftmailerExtension extends Extension
{
    /**
     * Loads the Swift Mailer configuration.
     *
     * Usage example:
     *
     *      <swiftmailer:config transport="gmail">
     *        <swiftmailer:username>fabien</swift:username>
     *        <swiftmailer:password>xxxxx</swift:password>
     *        <swiftmailer:spool path="/path/to/spool/" />
     *      </swiftmailer:config>
     *
     * @param array            $configs   An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('swiftmailer.xml');

        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        $mailers = array();
        foreach ($config['mailers'] as $name => $mailer) {
            $isDefaultMailer = $config['default_mailer'] === $name;
            $this->configureMailer($name, $mailer, $container, $isDefaultMailer);
            $mailers[$name] = sprintf('swiftmailer.mailer.%s', $name);
        }
        ksort($mailers);
        $container->setParameter('swiftmailer.mailers', $mailers);
        $container->setParameter('swiftmailer.default_mailer', $config['default_mailer']);

        $container->findDefinition('swiftmailer.data_collector')->addTag('data_collector', array('template' => '@Swiftmailer/Collector/swiftmailer.html.twig', 'id' => 'swiftmailer', 'priority' => 245));

        $container->setAlias('mailer', 'swiftmailer.mailer');
    }

    protected function configureMailer($name, array $mailer, ContainerBuilder $container, $isDefaultMailer = false)
    {
        if (null === $mailer['transport']) {
            $transport = 'null';
        } elseif ('gmail' === $mailer['transport']) {
            $mailer['encryption'] = 'ssl';
            $mailer['auth_mode'] = 'login';
            $mailer['host'] = 'smtp.gmail.com';
            $transport = 'smtp';
        } else {
            $transport = $mailer['transport'];
        }

        if (null !== $mailer['url']) {
            $parts = parse_url($mailer['url']);
            if (!empty($parts['scheme'])) {
                $transport = $parts['scheme'];
            }

            if (!empty($parts['user'])) {
                $mailer['username'] = $parts['user'];
            }
            if (!empty($parts['pass'])) {
                $mailer['password'] = $parts['pass'];
            }
            if (!empty($parts['host'])) {
                $mailer['host'] = $parts['host'];
            }
            if (!empty($parts['port'])) {
                $mailer['port'] = $parts['port'];
            }
            if (!empty($parts['query'])) {
                $query = array();
                parse_str($parts['query'], $query);
                if (!empty($query['encryption'])) {
                    $mailer['encryption'] = $query['encryption'];
                }
                if (!empty($query['auth_mode'])) {
                    $mailer['auth_mode'] = $query['auth_mode'];
                }
            }
        }
        unset($mailer['url']);

        $container->setParameter(sprintf('swiftmailer.mailer.%s.transport.name', $name), $transport);

        if (isset($mailer['disable_delivery']) && $mailer['disable_delivery']) {
            $transport = 'null';
            $container->setParameter(sprintf('swiftmailer.mailer.%s.delivery.enabled', $name), false);
        } else {
            $container->setParameter(sprintf('swiftmailer.mailer.%s.delivery.enabled', $name), true);
        }

        if (empty($mailer['port'])) {
            $mailer['port'] = 'ssl' === $mailer['encryption'] ? 465 : 25;
        }

        $this->configureMailerTransport($name, $mailer, $container, $transport, $isDefaultMailer);
        $this->configureMailerSpool($name, $mailer, $container, $transport, $isDefaultMailer);
        $this->configureMailerSenderAddress($name, $mailer, $container, $isDefaultMailer);
        $this->configureMailerAntiFlood($name, $mailer, $container, $isDefaultMailer);
        $this->configureMailerDeliveryAddress($name, $mailer, $container, $isDefaultMailer);
        $this->configureMailerLogging($name, $mailer, $container, $isDefaultMailer);

        // alias
        if ($isDefaultMailer) {
            $container->setAlias('swiftmailer.mailer', sprintf('swiftmailer.mailer.%s', $name));
            $container->setAlias('swiftmailer.transport', sprintf('swiftmailer.mailer.%s.transport', $name));
            $container->setParameter('swiftmailer.spool.enabled', $container->getParameter(sprintf('swiftmailer.mailer.%s.spool.enabled', $name)));
            $container->setParameter('swiftmailer.delivery.enabled', $container->getParameter(sprintf('swiftmailer.mailer.%s.delivery.enabled', $name)));
            $container->setParameter('swiftmailer.single_address', $container->getParameter(sprintf('swiftmailer.mailer.%s.single_address', $name)));
        }
    }

    protected function configureMailerTransport($name, array $mailer, ContainerBuilder $container, $transport, $isDefaultMailer = false)
    {
        foreach (array('encryption', 'port', 'host', 'username', 'password', 'auth_mode', 'timeout', 'source_ip', 'local_domain') as $key) {
            $container->setParameter(sprintf('swiftmailer.mailer.%s.transport.smtp.%s', $name, $key), $mailer[$key]);
        }

        $definitionDecorator = $this->createChildDefinition('swiftmailer.transport.eventdispatcher.abstract');
        $container
            ->setDefinition(sprintf('swiftmailer.mailer.%s.transport.eventdispatcher', $name), $definitionDecorator)
        ;

        if ('smtp' === $transport) {
            $authDecorator = $this->createChildDefinition('swiftmailer.transport.authhandler.abstract');
            $container
                ->setDefinition(sprintf('swiftmailer.mailer.%s.transport.authhandler', $name), $authDecorator)
                ->addMethodCall('setUsername', array(sprintf('%%swiftmailer.mailer.%s.transport.smtp.username%%', $name)))
                ->addMethodCall('setPassword', array(sprintf('%%swiftmailer.mailer.%s.transport.smtp.password%%', $name)))
                ->addMethodCall('setAuthMode', array(sprintf('%%swiftmailer.mailer.%s.transport.smtp.auth_mode%%', $name)));

            $bufferDecorator = $this->createChildDefinition('swiftmailer.transport.buffer.abstract');
            $container
                ->setDefinition(sprintf('swiftmailer.mailer.%s.transport.buffer', $name), $bufferDecorator);

            $configuratorDecorator = $this->createChildDefinition('swiftmailer.transport.smtp.configurator.abstract');
            $container
                ->setDefinition(sprintf('swiftmailer.transport.configurator.%s', $name), $configuratorDecorator)
                ->setArguments(array(
                    sprintf('%%swiftmailer.mailer.%s.transport.smtp.local_domain%%', $name),
                    new Reference('router.request_context', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ))
            ;

            $definitionDecorator = $this->createChildDefinition('swiftmailer.transport.smtp.abstract');
            $container
                ->setDefinition(sprintf('swiftmailer.mailer.%s.transport.smtp', $name), $definitionDecorator)
                ->setArguments(array(
                    new Reference(sprintf('swiftmailer.mailer.%s.transport.buffer', $name)),
                    array(new Reference(sprintf('swiftmailer.mailer.%s.transport.authhandler', $name))),
                    new Reference(sprintf('swiftmailer.mailer.%s.transport.eventdispatcher', $name)),
                ))
                ->addMethodCall('setHost', array(sprintf('%%swiftmailer.mailer.%s.transport.smtp.host%%', $name)))
                ->addMethodCall('setPort', array(sprintf('%%swiftmailer.mailer.%s.transport.smtp.port%%', $name)))
                ->addMethodCall('setEncryption', array(sprintf('%%swiftmailer.mailer.%s.transport.smtp.encryption%%', $name)))
                ->addMethodCall('setTimeout', array(sprintf('%%swiftmailer.mailer.%s.transport.smtp.timeout%%', $name)))
                ->addMethodCall('setSourceIp', array(sprintf('%%swiftmailer.mailer.%s.transport.smtp.source_ip%%', $name)))
                ->setConfigurator(array(new Reference(sprintf('swiftmailer.transport.configurator.%s', $name)), 'configure'))
            ;

            if (isset($mailer['stream_options'])) {
                $container->setParameter(sprintf('swiftmailer.mailer.%s.transport.smtp.stream_options', $name), $mailer['stream_options']);
                $definitionDecorator->addMethodCall('setStreamOptions', array(sprintf('%%swiftmailer.mailer.%s.transport.smtp.stream_options%%', $name)));
            }

            $container->setAlias(sprintf('swiftmailer.mailer.%s.transport', $name), sprintf('swiftmailer.mailer.%s.transport.%s', $name, $transport));
        } elseif ('sendmail' === $transport) {
            $bufferDecorator = $this->createChildDefinition('swiftmailer.transport.buffer.abstract');
            $container
                ->setDefinition(sprintf('swiftmailer.mailer.%s.transport.buffer', $name), $bufferDecorator);

            $configuratorDecorator = $this->createChildDefinition('swiftmailer.transport.smtp.configurator.abstract');
            $container
                ->setDefinition(sprintf('swiftmailer.transport.configurator.%s', $name), $configuratorDecorator)
                ->setArguments(array(
                    sprintf('%%swiftmailer.mailer.%s.transport.smtp.local_domain%%', $name),
                    new Reference('router.request_context', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ))
            ;

            $definitionDecorator = $this->createChildDefinition(sprintf('swiftmailer.transport.%s.abstract', $transport));
            $container
                ->setDefinition(sprintf('swiftmailer.mailer.%s.transport.%s', $name, $transport), $definitionDecorator)
                ->setArguments(array(
                    new Reference(sprintf('swiftmailer.mailer.%s.transport.buffer', $name)),
                    new Reference(sprintf('swiftmailer.mailer.%s.transport.eventdispatcher', $name)),
                ))
                ->setConfigurator(array(new Reference(sprintf('swiftmailer.transport.configurator.%s', $name)), 'configure'))
            ;

            $container->setAlias(sprintf('swiftmailer.mailer.%s.transport', $name), sprintf('swiftmailer.mailer.%s.transport.%s', $name, $transport));
        } elseif ('mail' === $transport) {
            $definitionDecorator = $this->createChildDefinition(sprintf('swiftmailer.transport.%s.abstract', $transport));
            $container
                ->setDefinition(sprintf('swiftmailer.mailer.%s.transport.%s', $name, $transport), $definitionDecorator)
                ->addArgument(new Reference(sprintf('swiftmailer.mailer.%s.transport.eventdispatcher', $name)))
            ;
            $container->setAlias(sprintf('swiftmailer.mailer.%s.transport', $name), sprintf('swiftmailer.mailer.%s.transport.%s', $name, $transport));
        } elseif ('null' === $transport) {
            $definitionDecorator = $this->createChildDefinition('swiftmailer.transport.null.abstract');
            $container
                ->setDefinition(sprintf('swiftmailer.mailer.%s.transport.null', $name, $transport), $definitionDecorator)
                ->setArguments(array(
                    new Reference(sprintf('swiftmailer.mailer.%s.transport.eventdispatcher', $name)),
                ))
            ;
            $container->setAlias(sprintf('swiftmailer.mailer.%s.transport', $name), sprintf('swiftmailer.mailer.%s.transport.%s', $name, $transport));
        } else {
            $container->setAlias(sprintf('swiftmailer.mailer.%s.transport', $name), sprintf('swiftmailer.mailer.transport.%s', $transport));
        }

        $definitionDecorator = $this->createChildDefinition('swiftmailer.mailer.abstract');
        $container
            ->setDefinition(sprintf('swiftmailer.mailer.%s', $name), $definitionDecorator)
            ->replaceArgument(0, new Reference(sprintf('swiftmailer.mailer.%s.transport', $name)))
        ;
    }

    protected function configureMailerSpool($name, array $mailer, ContainerBuilder $container, $transport, $isDefaultMailer = false)
    {
        if (isset($mailer['spool'])) {
            $type = $mailer['spool']['type'];
            if ('service' === $type) {
                $container->setAlias(sprintf('swiftmailer.mailer.%s.spool.service', $name), $mailer['spool']['id']);
            } else {
                foreach (array('path') as $key) {
                    $container->setParameter(sprintf('swiftmailer.spool.%s.%s.%s', $name, $type, $key), $mailer['spool'][$key].'/'.$name);
                }
            }

            $definitionDecorator = $this->createChildDefinition(sprintf('swiftmailer.spool.%s.abstract', $type));
            if ('file' === $type) {
                $container
                    ->setDefinition(sprintf('swiftmailer.mailer.%s.spool.file', $name), $definitionDecorator)
                    ->replaceArgument(0, sprintf('%%swiftmailer.spool.%s.file.path%%', $name))
                ;
            } elseif ('memory' === $type) {
                $container
                    ->setDefinition(sprintf('swiftmailer.mailer.%s.spool.memory', $name), $definitionDecorator)
                ;
            }
            $container->setAlias(sprintf('swiftmailer.mailer.%s.spool', $name), sprintf('swiftmailer.mailer.%s.spool.%s', $name, $type));

            $definitionDecorator = $this->createChildDefinition('swiftmailer.transport.spool.abstract');
            $container
                ->setDefinition(sprintf('swiftmailer.mailer.%s.transport.spool', $name), $definitionDecorator)
                ->setArguments(array(
                    new Reference(sprintf('swiftmailer.mailer.%s.transport.eventdispatcher', $name)),
                    new Reference(sprintf('swiftmailer.mailer.%s.spool', $name)),
                ))
            ;

            if (in_array($transport, array('smtp', 'mail', 'sendmail', 'null'))) {
                // built-in transport
                $transport = sprintf('swiftmailer.mailer.%s.transport.%s', $name, $transport);
            }
            $container->setAlias(sprintf('swiftmailer.mailer.%s.transport.real', $name), $transport);
            $container->setAlias(sprintf('swiftmailer.mailer.%s.transport', $name), sprintf('swiftmailer.mailer.%s.transport.spool', $name));
            $container->setParameter(sprintf('swiftmailer.mailer.%s.spool.enabled', $name), true);
            if (true === $isDefaultMailer) {
                $container->setAlias('swiftmailer.spool', sprintf('swiftmailer.mailer.%s.spool', $name));
                $container->setAlias('swiftmailer.transport.real', sprintf('swiftmailer.mailer.%s.transport.real', $name));
            }
        } else {
            $container->setParameter(sprintf('swiftmailer.mailer.%s.spool.enabled', $name), false);
        }
    }

    protected function configureMailerSenderAddress($name, array $mailer, ContainerBuilder $container, $isDefaultMailer = false)
    {
        if (isset($mailer['sender_address']) && $mailer['sender_address']) {
            $container->setParameter(sprintf('swiftmailer.mailer.%s.sender_address', $name), $mailer['sender_address']);
            $definitionDecorator = $this->createChildDefinition('swiftmailer.plugin.impersonate.abstract');
            $container
                ->setDefinition(sprintf('swiftmailer.mailer.%s.plugin.impersonate', $name), $definitionDecorator)
                ->setArguments(array(
                    sprintf('%%swiftmailer.mailer.%s.sender_address%%', $name),
                ))
            ;
            $container->getDefinition(sprintf('swiftmailer.mailer.%s.plugin.impersonate', $name))->addTag(sprintf('swiftmailer.%s.plugin', $name));
            if (true === $isDefaultMailer) {
                $container->setAlias('swiftmailer.plugin.impersonate', sprintf('swiftmailer.mailer.%s.plugin.impersonate', $name));
                $container->setParameter('swiftmailer.sender_address', $container->getParameter(sprintf('swiftmailer.mailer.%s.sender_address', $name)));
            }
        } else {
            $container->setParameter(sprintf('swiftmailer.mailer.%s.plugin.impersonate', $name), null);
        }
    }

    protected function configureMailerAntiFlood($name, array $mailer, ContainerBuilder $container, $isDefaultMailer = false)
    {
        if (isset($mailer['antiflood'])) {
            $container->setParameter(sprintf('swiftmailer.mailer.%s.antiflood.threshold', $name), $mailer['antiflood']['threshold']);
            $container->setParameter(sprintf('swiftmailer.mailer.%s.antiflood.sleep', $name), $mailer['antiflood']['sleep']);
            $definitionDecorator = $this->createChildDefinition('swiftmailer.plugin.antiflood.abstract');
            $container
                ->setDefinition(sprintf('swiftmailer.mailer.%s.plugin.antiflood', $name), $definitionDecorator)
                ->setArguments(array(
                    sprintf('%%swiftmailer.mailer.%s.antiflood.threshold%%', $name),
                    sprintf('%%swiftmailer.mailer.%s.antiflood.sleep%%', $name),
                ))
            ;
            $container->getDefinition(sprintf('swiftmailer.mailer.%s.plugin.antiflood', $name))->addTag(sprintf('swiftmailer.%s.plugin', $name));
            if (true === $isDefaultMailer) {
                $container->setAlias('swiftmailer.mailer.plugin.antiflood', sprintf('swiftmailer.mailer.%s.plugin.antiflood', $name));
            }
        }
    }

    protected function configureMailerDeliveryAddress($name, array $mailer, ContainerBuilder $container, $isDefaultMailer = false)
    {
        if (count($mailer['delivery_addresses']) > 0) {
            $container->setParameter(sprintf('swiftmailer.mailer.%s.single_address', $name), $mailer['delivery_addresses'][0]);
            $container->setParameter(sprintf('swiftmailer.mailer.%s.delivery_addresses', $name), $mailer['delivery_addresses']);
            $container->setParameter(sprintf('swiftmailer.mailer.%s.delivery_whitelist', $name), $mailer['delivery_whitelist']);
            $definitionDecorator = $this->createChildDefinition('swiftmailer.plugin.redirecting.abstract');
            $container
                ->setDefinition(sprintf('swiftmailer.mailer.%s.plugin.redirecting', $name), $definitionDecorator)
                ->setArguments(array(
                    sprintf('%%swiftmailer.mailer.%s.delivery_addresses%%', $name),
                    sprintf('%%swiftmailer.mailer.%s.delivery_whitelist%%', $name),
                ))
            ;
            $container->getDefinition(sprintf('swiftmailer.mailer.%s.plugin.redirecting', $name))->addTag(sprintf('swiftmailer.%s.plugin', $name));
            if (true === $isDefaultMailer) {
                $container->setAlias('swiftmailer.plugin.redirecting', sprintf('swiftmailer.mailer.%s.plugin.redirecting', $name));
            }
        } else {
            $container->setParameter(sprintf('swiftmailer.mailer.%s.single_address', $name), null);
        }
    }

    protected function configureMailerLogging($name, array $mailer, ContainerBuilder $container, $isDefaultMailer = false)
    {
        if ($mailer['logging']) {
            $container->getDefinition('swiftmailer.plugin.messagelogger.abstract');
            $definitionDecorator = $this->createChildDefinition('swiftmailer.plugin.messagelogger.abstract');
            $container
                ->setDefinition(sprintf('swiftmailer.mailer.%s.plugin.messagelogger', $name), $definitionDecorator)
            ;
            $container->getDefinition(sprintf('swiftmailer.mailer.%s.plugin.messagelogger', $name))->addTag(sprintf('swiftmailer.%s.plugin', $name));
            if (true === $isDefaultMailer) {
                $container->setAlias('swiftmailer.plugin.messagelogger', sprintf('swiftmailer.mailer.%s.plugin.messagelogger', $name));
            }
        }
    }

    /**
     * Returns the base path for the XSD files.
     *
     * @return string The XSD base path
     */
    public function getXsdValidationBasePath()
    {
        return __DIR__.'/../Resources/config/schema';
    }

    /**
     * Returns the namespace to be used for this extension (XML namespace).
     *
     * @return string The XML namespace
     */
    public function getNamespace()
    {
        return 'http://symfony.com/schema/dic/swiftmailer';
    }

    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return new Configuration($container->getParameter('kernel.debug'));
    }

    private function createChildDefinition($id)
    {
        if (class_exists('Symfony\Component\DependencyInjection\ChildDefinition')) {
            return new ChildDefinition($id);
        }

        return new DefinitionDecorator($id);
    }
}
