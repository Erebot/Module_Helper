Usage
=====

This section assumes default values are used for all triggers.
Please refer to :ref:`configuration options <configuration options>`
for more information on how to customize triggers.

..  _`commands_provided`:

Provided commands
-----------------

This module provides the following commands:

..  table:: Commands provided by |project|

    +-------------------------------+---------------------------------------+
    | Command                       | Description                           |
    +===============================+=======================================+
    | ``!help``                     | Gives a brief summary of usage.       |
    +-------------------------------+---------------------------------------+
    | :samp:`!help {Module}`        | Retrieves help on module *Module*.    |
    |                               | The name of the module **MUST** start |
    |                               | with an uppercase letter.             |
    +-------------------------------+---------------------------------------+
    | :samp:`!help {command}`       | Retrieves help on command *command*.  |
    |                               | The command's name **MAY NOT** start  |
    |                               | with an uppercase letter.             |
    +-------------------------------+---------------------------------------+
    | ``!help Erebot_Module_Helper``| Displays all available modules.       |
    +-------------------------------+---------------------------------------+
    | ``!help help``                | Same as if ``!help`` had been used.   |
    +-------------------------------+---------------------------------------+


Examples
--------

The listing below shows different sessions where commands have been used
to get help on other modules and commands, in the same order as in the
:ref:`array above <commands_provided>`.

..  sourcecode:: irc

    23:51:23 <@Clicky> !help
    23:51:24 < Erebot> Usage : "!help <Module> [commande]" ou "!help <commande>". Fournit de l'aide sur un module ou une commande donné. Utilisez "!help
                       Erebot_Module_Helper" pour voir la liste des modules actuellement chargés.

    23:53:35 <@Clicky> !help Erebot_Module_Roulette
    23:53:35 < Erebot> Fournit la commande !roulette qui vous fait jouer à la Roulette Russe.

    23:53:43 <@Clicky> !help roulette
    23:53:43 < Erebot> Usage : !roulette. Appuie sur la gachette du pistolet de la Roulette Russe.

    23:53:07 <@Clicky> !help Erebot_Module_Helper
    23:53:07 < Erebot> Usage : "!help <Module> [commande]". Les noms de modules doivent commencer par une majuscule, mais ne sont pas sensibles à la casse.
                       Les modules suivants sont chargés : Erebot_Module_AutoJoin, Erebot_Module_AZ, Erebot_Module_TriggerRegistry, Erebot_Module_Admin,
                       Erebot_Module_AutoConnect, Erebot_Module_Countdown, Erebot_Module_Helper, Erebot_Module_CtcpResponder,
    23:53:08 < Erebot> Erebot_Module_GoF, Erebot_Module_IrcConnector, Erebot_Module_IrcTracker, Erebot_Module_LagChecker, Erebot_Module_Math,
                       Erebot_Module_PhpFilter, Erebot_Module_PingReply, Erebot_Module_RateLimiter, Erebot_Module_Roulette,
                       Erebot_Module_ServerCapabilities, Erebot_Module_TV, Erebot_Module_Uno, Erebot_Module_WatchList, Erebot_Module_WebGetter &
    23:53:09 < Erebot> Erebot_Module_Wordlists.

    23:54:09 <@Clicky> !help help
    23:54:09 < Erebot> Usage : "!help <Module> [commande]" ou "!help <commande>". Fournit de l'aide sur un module ou une commande donné. Utilisez "!help
                       Erebot_Module_Helper" pour voir la liste des modules actuellement chargés.


..  vim: ts=4 et
