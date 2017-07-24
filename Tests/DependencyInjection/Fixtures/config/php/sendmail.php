<?php

$container->loadFromExtension('swiftmailer', array(
    'transport' => 'sendmail',
    'local_domain' => 'local.example.org',
    'command' => '/usr/sbin/sendmail -bs'
));
