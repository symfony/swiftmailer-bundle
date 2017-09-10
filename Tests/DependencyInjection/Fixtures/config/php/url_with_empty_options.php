<?php

$container->loadFromExtension('swiftmailer', array(
    'default_mailer' => 'smtp_mailer',
    'mailers' => array(
        'smtp_mailer' => array(
            'url' => 'smtp://example.com:12345?username=&password=&encryption=&auth_mode=',
        ),
    ),
));
