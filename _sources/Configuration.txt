Configuration
=============

.. _`configuration options`:

Options
-------

This module provides only one configuration option.

..  table:: Options for |project|

    +---------------+--------+---------------+------------------------------+
    | Name          | Type   | Default value | Description                  |
    +===============+========+===============+==============================+
    | trigger       | string | "help"        | The command to use to get    |
    |               |        |               | help on other modules and    |
    |               |        |               | commands.                    |
    +---------------+--------+---------------+------------------------------+


Example
-------

The recommended way to use this module is to have it loaded at the general
configuration level and to disable it only for specific networks, if needed.

The listing below shows how to change this module's trigger so that help
becomes available using the command ``!helpme`` (instead of ``!help``).

..  parsed-code:: xml

    <?xml version="1.0"?>
    <configuration
      xmlns="http://localhost/Erebot/"
      version="..."
      language="fr-FR"
      timezone="Europe/Paris"
      commands-prefix="!">

      <modules>
        <!-- Other modules ignored for clarity. -->

        <!-- We only change the default command name. -->
        <module name="|project|">
          <param name="trigger" value="helpme" />
        </module>
      </modules>

      <!-- Rest of the configuration (networks, etc.) -->
    </configuration>

.. vim: ts=4 et
