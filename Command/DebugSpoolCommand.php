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

use DirectoryIterator;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Send Emails from the spool.
 *
 * @author Adam Zielinski <kontakt@azielinski.info>
 */
class DebugSpoolCommand extends ContainerAwareCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('swiftmailer:spool:debug')
            ->setDescription('Displays messags in the spool')
            ->addOption('max-recipients', 5, InputOption::VALUE_OPTIONAL, 'The maximum number of recipients listed per message. No limit if set to 0.')
            ->addOption('max-messages', 10, InputOption::VALUE_OPTIONAL, 'The maximum number of messages to show. No limit if set to 0.')
            ->addOption('mailer', null, InputOption::VALUE_OPTIONAL, 'The mailer name.')
            ->setHelp(<<<EOF
The <info>swiftmailer:spool:dump</info> command displays emails from the spool.

<info>php app/console swiftmailer:spool:debug --max-messages=10 --mailer=default</info>

EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getOption('mailer');
        if ($name) {
            $this->processMailer($name, $input, $output);
        } else {
            $mailers = array_keys($this->getContainer()->getParameter('swiftmailer.mailers'));
            foreach ($mailers as $name) {
                $this->processMailer($name, $input, $output);
            }
        }
    }

    private function processMailer($name, $input, $output)
    {
        if (!$this->getContainer()->has(sprintf('swiftmailer.mailer.%s', $name))) {
            throw new \InvalidArgumentException(sprintf('The mailer "%s" does not exist.', $name));
        }

        $output->write(sprintf('<info>[%s]</info> Processing <info>%s</info> mailer... ', date('Y-m-d H:i:s'), $name));
        if ($this->getContainer()->getParameter(sprintf('swiftmailer.mailer.%s.spool.enabled', $name))) {
            $mailer = $this->getContainer()->get(sprintf('swiftmailer.mailer.%s', $name));
            $transport = $mailer->getTransport();
            if ($transport instanceof \Swift_Transport_SpoolTransport) {
                $spool = $transport->getSpool();
                if (!($spool instanceof \Swift_FileSpool)) {
                    $output->writeln(sprintf('Skipping mailer <comment>%s</comment> which uses "%s" spool (only '.
                        'Swift_FileSpool is supported) ', $name, get_class($spool)));

                    return;
                }

                $this->processSpool($name, $spool, $input, $output);
            }
        } else {
            $output->writeln('No email to dump as the spool is disabled.');
        }
    }

    private function processSpool($name, $spool, $input, $output)
    {
        $spoolPath = $this->getContainer()->getParameter(sprintf('swiftmailer.spool.%s.file.path', $name));
        $directoryIterator = new DirectoryIterator($spoolPath);

        $nbTruncatedRecipients = $nbProcessedFiles = 0;
        $messagesLimit = $input->getOption('max-messages');
        $recipientsLimit = $input->getOption('max-recipients');

        // Collect spooled messages
        $spooledEmails = array();
        foreach ($directoryIterator as $file) {
            $file = $file->getRealPath();

            if (substr($file, -8) != '.message') {
                continue;
            }

            ++$nbProcessedFiles;
            if ($messagesLimit && $nbProcessedFiles > $messagesLimit) {
                continue;
            }

            $message = unserialize(file_get_contents($file));

            $from = $this->formatEmail($message->getFrom());
            $to = $message->getTo();
            if ($recipientsLimit) {
                $to = array_slice($to, 0, $recipientsLimit);
            }
            $to = $this->formatEmails($to);

            $truncatedRecipients = count($message->getTo()) - count($to);
            $nbTruncatedRecipients += $truncatedRecipients;

            $spooledEmails[] = array(
                $to[0],
                $from,
                $message->getSubject(),
                $name,
                date("d-m-Y H:i:s", $message->getHeaders()->get('Date')->getTimestamp()),
            );

            if (count($message->getTo()) > 1) {
                foreach (array_slice($to, 1) as $email) {
                    $spooledEmails[] = array($email, '', '', '', '');
                }
                if ($truncatedRecipients) {
                    $spooledEmails[] = array(sprintf('+%d more', $truncatedRecipients), '', '', '', '');
                }
            }
        }

        // Display spooled messages
        if (count($spooledEmails)) {
            $output->writeln('');

            $table = new Table($output);
            $table
                ->setHeaders(array('To', 'From', 'Subject', 'Mailer', 'Sent at'))
                ->setRows($spooledEmails)
                ->setStyle('borderless')
                ->render($output)
            ;

            $output->writeln(sprintf('<comment>%d</comment> emails total in spool', $nbProcessedFiles));

            if ($messagesLimit) {
                $diff = $nbProcessedFiles - $messagesLimit;
                if ($diff > 0) {
                    $output->writeln(sprintf('<comment>%d</comment> emails not displayed here, use --max-messages option to show them', $diff));
                }
            }
            if ($recipientsLimit && $nbTruncatedRecipients > 0) {
                $output->writeln(sprintf('<comment>%d</comment> recipients not displayed here, use --max-recipients option to show them', $nbTruncatedRecipients));
            }
        } else {
            $output->writeln('No email to dump as the spool is empty.');
        }
    }

    // Private methods --------------------------------------

    private function formatEmails($emails)
    {
        $formatted = array();
        foreach ($emails as $email => $name) {
            $formatted[] = $this->formatEmail(array($email => $name));
        }

        return $formatted;
    }

    private function formatEmail($email)
    {
        if (is_array($email)) {
            list($email, $name) = array_merge(array_keys($email), array_values($email));
            if ($name) {
                $email = sprintf('%s <%s>', $name, $email);
            }
        } elseif (!$email) {
            $email = '<EMPTY>';
        }

        return $email;
    }
}
