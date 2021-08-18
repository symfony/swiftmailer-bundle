Installation
============

**Swiftmailer will stop being maintained at the end of November 2021.**

Please, move to `Symfony Mailer <https://symfony.com/doc/current/mailer.html>`_ at your earliest convenience.
`Symfony Mailer <https://symfony.com/doc/current/mailer.html>`_ is the next evolution of Swiftmailer.
It provides the same features with support for modern PHP code and support for third-party providers.

Step 1: Download the Bundle
---------------------------

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

.. code-block:: bash

    $ composer require symfony/swiftmailer-bundle "~2.3"

This command requires you to have Composer installed globally, as explained
in the `installation chapter`_ of the Composer documentation.

Step 2: Enable the Bundle
-------------------------

Then, enable the bundle by adding it to the list of registered bundles
in the ``app/AppKernel.php`` file of your project:

.. code-block:: php

    <?php
    // app/AppKernel.php

    // ...
    class AppKernel extends Kernel
    {
        public function registerBundles()
        {
            $bundles = array(
                // ...

                new Symfony\Bundle\SwiftmailerBundle\SwiftmailerBundle(),
            );

            // ...
        }

        // ...
    }

.. _`installation chapter`: https://getcomposer.org/doc/00-intro.md
