<?php

$container->loadFromExtension('swiftmailer', array(
    'transport' => 'sendmail',
    'url' => '%env(SWIFTMAILER_URL)%',
));
