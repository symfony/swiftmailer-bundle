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

use Symfony\Component\Routing\RequestContext;

/**
 * Service configurator.
 */
class AbstractSmtpTransportConfigurator
{
    /**
     * @var string
     */
    protected $localDomain;

    /**
     * @var RequestContext
     */
    protected $requestContext;

    /**
     * Sets the local domain based on the current request context.
     *
     * @param string         $localDomain    Fallback value if there is no request context.
     * @param RequestContext $requestContext
     */
    public function __construct($localDomain, RequestContext $requestContext = null)
    {
        $this->localDomain = $localDomain;
        $this->requestContext = $requestContext;
    }

    public function configure(\Swift_Transport_AbstractSmtpTransport $transport)
    {
        if ($this->localDomain) {
            $transport->setLocalDomain($this->localDomain);
        } elseif ($this->requestContext) {
            $transport->setLocalDomain($this->requestContext->getHost());
        }
    }
}
