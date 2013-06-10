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

class TestCase extends \PHPUnit_Framework_TestCase
{
    public static function setUpBeforeClass()
    {
        if (!class_exists('Swift_Mailer')) {
            self::markTestSkipped('Swiftmailer is not available.');
        }
    }
}
