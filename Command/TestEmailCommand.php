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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\ValidatorInterface;

/**
 * send an e-mail to test the current configuration
 *
 * @author Maxime Corson <maxime.corson@spyrit.net> - Spyrit SI
 * @author Charles Sanquer <charles.sanquer@gmail.com>
 */
class TestEmailCommand extends ContainerAwareCommand
{

    /**
     * @see Command
     */
    protected function configure()
    {
        $this->setName('swiftmailer:test')
             ->setDescription('Send e-mail to test the server configuration')
             ->addArgument('email',InputArgument::OPTIONAL, 'The address where to send the test e-mail')
             ->addOption('subject', 'u', InputOption::VALUE_REQUIRED, 'The test email subject.', 'Symfony 2 Test Email')
             ->addOption('from', 'f', InputOption::VALUE_REQUIRED, 'The test email sender address.', 'noreply@symfony.com')
             ->addOption('mailer', null, InputOption::VALUE_OPTIONAL, 'The mailer name.')
             ->setHelp(<<<EOF
The <info>%command.name%</info> command send an e-email to test the server configuration.

<info>php %command.full_name% --from=noreply@my-company.com --subject='my test email' --mailer=default [my.email@my-company.com]</info>

EOF
        );
    }

    /**
     * @see Command
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        if (!$input->getArgument('email')) {
            $toAddress = $this->getHelper('dialog')->askAndValidate(
                $output,
                '<question>email address to send the test email :</question> ',
                function($toAddress) {
                    if (empty($toAddress)) {
                        throw new Exception('You have to provide an destination email address.');
                    }

                    return $toAddress;
                }
            );
            $input->setArgument('email', $toAddress);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getOption('mailer');
        if ($name) {
            $this->testMailer($name, $input, $output);
        } else {
            $mailers = array_keys($this->getContainer()->getParameter('swiftmailer.mailers'));
            foreach ($mailers as $name) {
                $this->testMailer($name, $input, $output);
            }
        }
    }

    protected function testMailer($name, InputInterface $input, OutputInterface $output)
    {
        if (!$this->getContainer()->has(sprintf('swiftmailer.mailer.%s', $name))) {
            throw new \InvalidArgumentException(sprintf('The mailer "%s" does not exist.', $name));
        }

        $mailer = $this->getContainer()->get(sprintf('swiftmailer.mailer.%s', $name));
        $transport = $mailer->getTransport();

        if ($transport instanceof \Swift_Transport_NullTransport) {
            $output->writeln('<error>Unable to send the test email : the mailer transport is currently a Null transport.</error>');

            return 1;
        }

        $fromAddress = $input->getOption('from');
        $toAddress = $input->getArgument('email');

        $validator = $this->getContainer()->get('validator');
        if ($validator instanceof ValidatorInterface) {
            $emailConstraint = new Constraints\Email();
            $errors = $validator->validateValue($toAddress, $emailConstraint);
            if (count($errors)) {
                $output->writeln('<error>You have to provide a valid destination email address.</error>');

                return 1;
            }

            $errors = $validator->validateValue($fromAddress, $emailConstraint);
            if (count($errors)) {
                $output->writeln('<error>You have to provide a valid sender email address.</error>');

                return 1;
            }
        }

        $output->writeln(sprintf('Testing <info>%s</info> mailer... ', $name));
        $single = $this->getContainer()->getParameterBag()->get(sprintf('swiftmailer.mailer.%s.single_address', $name));
        $output->writeln('The test email will be sent to <info>'.$toAddress.'</info> '.(!empty($single) ? ' (really sent to <comment>'.$single.'</comment>).': '.'));

        $sentAt = date('l Y-m-d H:i:s');
        $message = \Swift_Message::newInstance()
            ->setSubject($input->getOption('subject'))
            ->setFrom($fromAddress)
            ->setTo($toAddress)
            ->setBody('This email has been auto generated by Symfony2 Swiftmailer Bundle and sent on '.$sentAt.' by '.$name.' mailer');

        if (!$mailer->send($message)) {
            $output->writeln('<error>Unable to send the test email : please check the '.$name.' mailer configuration</error>');

            return 1;
        }

        $output->writeln('The test email has been sent on <info>'.$sentAt.'</info>');

        $withSpool = (bool) $this->getContainer()->getParameterBag()->get(sprintf('swiftmailer.mailer.%s.spool.enabled', $name));

        if ($withSpool &&
            $mailer->getTransport() instanceof \Swift_Transport_SpoolTransport &&
            $this->getHelperSet()->get('dialog')->askConfirmation($output, '<question>Do you really want to flush the '.$name.' mailer spool ? (y/n) [n] </question>', false)
        ) {
            $output->writeln('<comment>flushing '.$name.' spool queue...</comment>');
            $sent = $mailer->getTransport()->getSpool()->flushQueue($this->getContainer()->get(sprintf('swiftmailer.mailer.%s.transport.real', $name)));
            $output->writeln(sprintf('<comment>%s</comment> <info>email'.($sent > 1 ? 's' : '').'</info> sent by flushing the '.$name.' mailer spool.', (int) $sent));
        }
    }
}
