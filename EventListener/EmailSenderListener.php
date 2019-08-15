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

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Console\ConsoleEvents;
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

    private $logger;

    private $wasExceptionThrown = false;

    public function __construct(ContainerInterface $container, LoggerInterface $logger = null)
    {
        $this->container = $container;
        $this->logger = $logger;
    }

    public function onException()
    {
        $this->wasExceptionThrown = true;
    }

    public function onTerminate()
    {
        if (!$this->container->has('mailer') || $this->wasExceptionThrown) {
            return;
        }
        $mailers = array_keys($this->container->getParameter('swiftmailer.mailers'));
        foreach ($mailers as $mailerName) {
            $this->flushQueue($mailerName);
        }
    }

    private function flushQueue(string $mailerName)
    {
        $initialized = !method_exists($this->container, 'initialized')
            || $this->container->initialized(sprintf('swiftmailer.mailer.%s', $mailerName));
        if (!$initialized) {
            return;
        }

        $spoolIsEnabled = $this->container->getParameter(sprintf('swiftmailer.mailer.%s.spool.enabled', $mailerName));
        if (!$spoolIsEnabled) {
            return;
        }

        $mailer = $this->container->get(sprintf('swiftmailer.mailer.%s', $mailerName));
        $transport = $mailer->getTransport();

        $isSpollTransport = $transport instanceof \Swift_Transport_SpoolTransport;
        if (!$isSpollTransport) {
            return;
        }

        $spool = $transport->getSpool();
        $isMemorySpoll = $spool instanceof \Swift_MemorySpool;
        if (!$isMemorySpoll) {
            return;
        }

        try {
            $spool->flushQueue($this->container->get(sprintf('swiftmailer.mailer.%s.transport.real', $mailerName)));
        } catch (\Swift_TransportException $exception) {
            if (null !== $this->logger) {
                $this->logger->error(sprintf('Exception occurred while flushing email queue: %s', $exception->getMessage()));
            }
        }
    }

    public static function getSubscribedEvents()
    {
        $listeners = [
            KernelEvents::EXCEPTION => 'onException',
            KernelEvents::TERMINATE => 'onTerminate',
        ];

        if (class_exists('Symfony\Component\Console\ConsoleEvents')) {
            $listeners[class_exists('Symfony\Component\Console\Event\ConsoleErrorEvent') ? ConsoleEvents::ERROR : ConsoleEvents::EXCEPTION] = 'onException';
            $listeners[ConsoleEvents::TERMINATE] = 'onTerminate';
        }

        return $listeners;
    }

    public function reset()
    {
        $this->wasExceptionThrown = false;
    }
}
