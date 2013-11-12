<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SwiftmailerBundle\Tests;

use Symfony\Bundle\SwiftmailerBundle\Tests\TestCase;
use Symfony\Bundle\SwiftmailerBundle\EventListener\EmailSenderListener;
use Symfony\Component\HttpKernel\KernelEvents;

class EmailSenderListenerTest extends TestCase
{
    public function testIfCorrectEventsAreAttached()
    {
        $actual = EmailSenderListener::getSubscribedEvents();
        $expected = array(
            KernelEvents::TERMINATE  => 'onTerminate'
        );

        $this->assertEquals($expected, $actual);
    }
}
