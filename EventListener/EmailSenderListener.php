<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SwiftmailerBundle\EventListener;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\IntrospectableContainerInterface;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sends emails for the memory spool.
 *
 * Emails are sent on the kernel.terminate event.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class EmailSenderListener implements EventSubscriberInterface
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function onKernelTerminate(PostResponseEvent $event)
    {
        if (!$this->container->has('mailer')) {
            return;
        }
        $mailers = array_keys($this->container->getParameter('swiftmailer.mailers'));
        foreach ($mailers as $name) {
            if ($this->container instanceof IntrospectableContainerInterface ? $this->container->initialized(sprintf('swiftmailer.mailer.%s', $name)) : true) {
                if ($this->container->getParameter(sprintf('swiftmailer.mailer.%s.spool.enabled', $name))) {
                    $mailer = $this->container->get(sprintf('swiftmailer.mailer.%s', $name));
                    $transport = $mailer->getTransport();
                    if ($transport instanceof \Swift_Transport_SpoolTransport) {
                        $spool = $transport->getSpool();
                        if ($spool instanceof \Swift_MemorySpool) {
                            $spool->flushQueue($this->container->get(sprintf('swiftmailer.mailer.%s.transport.real', $name)));
                        }
                    }
                }
            }
        }
    }

    static public function getSubscribedEvents()
    {
        return array(KernelEvents::TERMINATE => 'onKernelTerminate');
    }
}
