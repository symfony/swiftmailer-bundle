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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A console command for creating and sending simple emails
 *
 * @author Gusakov Nikita <dev@nkt.me>
 */
class NewEmailCommand extends ContainerAwareCommand
{
    /**
     * The mail parts
     * @var array
     */
    private $options = array('from', 'to', 'body', 'subject', 'content-type', 'charset');

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('swiftmailer:email:new')
            ->setDescription('Send simple email message')
            ->addOption('from', 'f', InputOption::VALUE_OPTIONAL, 'The from address of the message')
            ->addOption('to', 't', InputOption::VALUE_OPTIONAL, 'The to address of the message')
            ->addOption('body', 'b', InputOption::VALUE_OPTIONAL, 'The body of the message')
            ->addOption('subject', 'sub', InputOption::VALUE_OPTIONAL, 'The subject of the message')
            ->addOption('content-type', 'ct', InputOption::VALUE_OPTIONAL, 'The body content type of the message', 'text/html')
            ->addOption('charset', null, InputOption::VALUE_OPTIONAL, 'The body charset of the message', 'UTF8')
            ->setHelp(<<<EOF
The <info>swiftmailer:email:new</info> command creates and send simple email message.

EOF
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $options = array();

        $dialog = $this->getHelper('dialog');
        foreach ($this->options as $option) {
            $default = $input->getOption($option);
            $options[$option] = $dialog->ask(
                $output,
                sprintf('<question>%s</question><info>[%s]</info>: ', ucfirst($option), $default),
                $default
            );
        }

        $message = \Swift_Message::newInstance(
            $options['subject'],
            $options['body'],
            $options['content-type'],
            $options['charset']
        )
            ->setFrom($options['from'])
            ->setTo($options['to']);
        $transport = $this->getContainer()->get('mailer')->getTransport();
        $transport->start();
        $output->writeln(sprintf('<info>Sent %s emails<info>', $transport->send($message)));
        $transport->stop();
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled()
    {
        return $this->getContainer()->has('mailer');
    }
}