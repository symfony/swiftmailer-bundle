<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SwiftmailerBundle\Transport;

use Swift_Events_EventDispatcher as EventDispatcher;
use Swift_Mime_SimpleMessage;
use Swift_Spool as Spool;
use Swift_Transport as Transport;
use Swift_Transport_SpoolTransport as SpoolTransport;

class FlushingSpoolTransport extends SpoolTransport
{
    /**
     * @var Transport
     */
    private $transport;

    /**
     * @var bool
     */
    private $instantFlush = false;

    public function __construct(EventDispatcher $eventDispatcher, Spool $spool, Transport $transport)
    {
        $this->transport = $transport;

        parent::__construct($eventDispatcher, $spool);
    }

    public function enableInstantFlush()
    {
        $this->instantFlush = true;
        $this->flushSpool();
    }

    public function disableInstantFlush()
    {
        $this->instantFlush = false;
    }

    /**
     * Sends messages all spooled messages.
     *
     * @return int The number of sent emails
     */
    public function flushSpool()
    {
        return $this->getSpool()->flushQueue($this->transport);
    }

    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        $sent = parent::send($message, $failedRecipients);

        if ($this->instantFlush) {
            $this->flushSpool();
        }

        return $sent;
    }
}
