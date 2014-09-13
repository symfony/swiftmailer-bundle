<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SwiftmailerBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A console command for retrieving information about mailers
 *
 * @author Jérémy Romey <jeremy@free-agent.fr>
 * @author Saša Stamenković <umpirsky@gmail.com>
 */
class DebugCommand extends ContainerAwareCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('swiftmailer:debug')
            ->setDefinition(array(
                new InputArgument('name', InputArgument::OPTIONAL, 'A mailer name'),
            ))
            ->setDescription('Displays current mailers for an application')
            ->setHelp(<<<EOF
The <info>%command.name%</info> displays the configured mailers:

  <info>php %command.full_name% mailer-name</info>
EOF
            )
        ;
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('name');

        if ($name && !$this->getContainer()->has(sprintf('swiftmailer.mailer.%s', $name))) {
            throw new \InvalidArgumentException(sprintf('The mailer "%s" does not exist.', $name));
        }

        $mailers = $this->getContainer()->getParameter('swiftmailer.mailers');

        $table = $this->getHelperSet()->get('table');
        $table->setHeaders(array('Name', 'Transport', 'Spool', 'Delivery', 'Single Address'));

        foreach ($mailers as $name => $mailer) {
            if ($input->getArgument('name') && $input->getArgument('name') !== $name) {
                continue;
            }

            $transport = $this->getContainer()->getParameter(sprintf('swiftmailer.mailer.%s.transport.name', $name));
            $spool = $this->getContainer()->getParameter(sprintf('swiftmailer.mailer.%s.spool.enabled', $name)) ? 'YES' : 'NO';
            $delivery = $this->getContainer()->getParameter(sprintf('swiftmailer.mailer.%s.delivery.enabled', $name)) ? 'YES' : 'NO';
            $singleAddress = $this->getContainer()->getParameter(sprintf('swiftmailer.mailer.%s.single_address', $name));
            if ($this->isDefaultMailer($name)) {
                $name = sprintf('%s (default mailer)', $name);
            }

            $table->addRow(array($name, $transport, $spool, $delivery, $singleAddress));
        }

        $table->render($output);
    }

    private function isDefaultMailer($name)
    {
        return ($this->getContainer()->getParameter('swiftmailer.default_mailer') == $name || 'default' == $name) ? true : false;
    }
}
