<?php
/*
    This file is part of Erebot.

    Erebot is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Erebot is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Erebot.  If not, see <http://www.gnu.org/licenses/>.
*/

namespace Erebot\Module;

/**
 * \brief
 *      A module that can be used by other modules
 *      to register a method to call whenever someone
 *      asks for help on them.
 */
class Helper extends \Erebot\Module\Base
{
    /// Token associated with this module's trigger.
    protected $trigger;

    /// Handler used by this module to detect help requests.
    protected $handler;

    /// Maps each module to the method that handles help requests for it.
    protected $helpCallbacks;


    /**
     * This method is called whenever the module is (re)loaded.
     *
     * \param int $flags
     *      A bitwise OR of the Erebot::Module::Base::RELOAD_*
     *      constants. Your method should take proper actions
     *      depending on the value of those flags.
     *
     * \note
     *      See the documentation on individual RELOAD_*
     *      constants for a list of possible values.
     */
    public function reload($flags)
    {
        if (!($flags & self::RELOAD_INIT)) {
            $registry =& $this->connection->getModule(
                '\\Erebot\\Module\\TriggerRegistry'
            );
            $this->connection->removeEventHandler($this->handler);
            $registry->freeTriggers($this->trigger, $registry::MATCH_ANY);
        }

        if ($flags & self::RELOAD_HANDLERS) {
            $registry = $this->connection->getModule(
                '\\Erebot\\Module\\TriggerRegistry'
            );
            $trigger        = $this->parseString('trigger', 'help');
            $this->trigger = $registry->registerTriggers($trigger, $registry::MATCH_ANY);
            if ($this->trigger === null) {
                $fmt = $this->getFormatter(false);
                throw new \Exception($fmt->_('Could not register Help trigger'));
            }

            $this->handler = new \Erebot\EventHandler(
                \Erebot\CallableWrapper::wrap(array($this, 'handleHelp')),
                new \Erebot\Event\Match\All(
                    new \Erebot\Event\Match\Type(
                        '\\Erebot\\Interfaces\\Event\\Base\\TextMessage'
                    ),
                    new \Erebot\Event\Match\Any(
                        new \Erebot\Event\Match\TextStatic($trigger, true),
                        new \Erebot\Event\Match\TextWildcard($trigger.' *', true)
                    )
                )
            );
            $this->connection->addEventHandler($this->handler);
        }

        if ($flags & self::RELOAD_MEMBERS) {
            // Add help support for the Helper module itself.
            // This has to be done by hand, because the module
            // may not be registered for this connection yet.
            $this->realRegisterHelpMethod(
                $this,
                \Erebot\CallableWrapper::wrap(array($this, 'getHelp'))
            );
        }
    }

    /**
     * Registers a method to call back whenever
     * someone requests help on a specific module.
     *
     * \param Erebot::Module::Base $module
     *      The module the method provides help for.
     *
     * \param Erebot::CallableInterface $callback
     *      The method/function to call whenever
     *      someone asks for help on that particular
     *      module or a command provided by it.
     *
     * \retval bool
     *      \b true if the call succeeded,
     *      \b false otherwise.
     */
    public function realRegisterHelpMethod(
        \Erebot\Module\Base $module,
        \Erebot\CallableInterface $callback
    ) {
        try {
            /// @FIXME This is pretty intrusive...
            $reflector  = new \ReflectionObject($callback);
            $reflector  = $reflector->getProperty('callable');
            $reflector->setAccessible(true);
            $callable   = $reflector->getValue($callback);
            $reflector->setAccessible(false);
            $reflector  = new \ReflectionParameter($callable, 0);
        } catch (\Exception $e) {
            $bot    = $this->connection->getBot();
            $logger = \Plop::getInstance();
            $logger->exception($bot->gettext('Exception:'), $e);
            return false;
        }

        $cls        = $reflector->getClass();
        if ($cls === null || !$cls->implementsInterface(
            '\\Erebot\\Interfaces\\Event\\Base\\MessageCapable'
        )) {
            throw new \Erebot\InvalidValueException('Invalid signature');
        }

        $this->helpCallbacks[static::normalizeModule(get_class($module))] = $callback;
        return true;
    }

    /**
     * Provides help about this module.
     *
     * \param Erebot::Interfaces::Event::Base::TextMessage $event
     *      Some help request.
     *
     * \param Erebot::Interfaces::TextWrapper $words
     *      Parameters passed with the request. This is the same
     *      as this module's name when help is requested on the
     *      module itself (in opposition with help on a specific
     *      command provided by the module).
     */
    public function getHelp(
        \Erebot\Interfaces\Event\Base\TextMessage $event,
        \Erebot\Interfaces\TextWrapper $words
    ) {
        if ($event instanceof \Erebot\Interfaces\Event\Base\PrivateMessage) {
            $target = $event->getSource();
            $chan   = null;
        } else {
            $target = $chan = $event->getChan();
        }

        $fmt        = $this->getFormatter($chan);
        $trigger    = $this->parseString('trigger', 'help');
        $moduleName = get_called_class();

        if ($words[0] !== $moduleName || (isset($words[1]) && $words[1] != $trigger)) {
            return false;
        }

        // "!help Helper <help_trigger>"

        $msg = $fmt->_(
            '<b>Usage</b>: "!<var name="trigger"/> &lt;<u>Module</u>&gt; '.
            '[<u>command</u>]" or "!<var name="trigger"/> '.
            '&lt;<u>command</u>&gt;". Provides help about a particular '.
            'module or command. Module names must start with an '.
            'uppercase letter. The following modules are currently loaded: '.
            '<for from="modules" item="module"><b><var name="module"/></b>'.
            '</for>.',
            array(
                'this' => get_class(),
                'modules' => $this->getModules($chan),
                'trigger' => $trigger,
            )
        );
        $this->sendMessage($target, $msg);
        return true;
    }

    /**
     * Checks whether the given module exists and whether
     * an help callback has been registered for that module.
     *
     * \param Erebot::StylingInterface $fmt
     *      Formatter for messages produced by this method.
     *
     * \param string $target
     *      Some user's nickname or IRC channel name messages
     *      emitted by this method will be sent to.
     *
     * \param mixed $chan
     *      Either the name of an IRC channel or \b null.
     *      This is used to retrieve a list of all modules
     *      enabled for that channel.
     *
     * \param string $moduleName
     *      Name of the module the help request is about.
     *
     * \retval bool
     *      \b true if the given module exists and some callback
     *      was registered to handle help requests for it, or
     *      \b false otherwise.
     */
    protected function checkCallback(\Erebot\StylingInterface $fmt, $target, $chan, $moduleName)
    {
        $found          = false;
        $chanModules    = $this->getModules($chan);
        $normName       = static::normalizeModule($moduleName);

        if (!in_array($normName, $chanModules)) {
            $msg = $fmt->_(
                'No such module <b><var name="request"/></b>. '.
                'Available modules: <for from="modules" item="module">'.
                '<b><var name="module"/></b></for>.',
                array(
                    'request' => $moduleName,
                    'modules' => $chanModules,
                )
            );
            $this->sendMessage($target, $msg);
            return false;
        }

        if (!isset($this->helpCallbacks[$normName])) {
            $msg = $fmt->_(
                'No help available on module <b><var name="module"/></b>.',
                array('module' => $moduleName)
            );
            $this->sendMessage($target, $msg);
            return false;
        }
        return true;
    }

    /**
     * Extracts the name of a module from some
     * help request.
     *
     * \param Erebot::Interfaces::TextWrapper $text
     *      Text of the help request from which
     *      the module name will be extracted.
     *
     * \retval mixed
     *      Either the name of the module the
     *      help request relates to or \b null
     *      if the request is about a command.
     *
     * \post
     *      If the request was related to a
     *      module, the module's name is removed
     *      from the request.
     */
    protected static function getModuleName(\Erebot\Interfaces\TextWrapper &$text)
    {
        // If the first letter of the first word is in uppercase,
        // this is a request for help on a module (!help Module).
        $first = substr($text[0], 0, 1);
        if ($first !== false && $first === strtoupper($first) || $first === '\\') {
            $moduleName = static::normalizeModule($text[0]);
            $text = $text->getTokens(1); // Remove module name.
            return $moduleName;
        }
        return null;
    }

    /**
     * Handles a request for help on some module/command.
     *
     * \param Erebot::Interfaces::EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot::Interfaces::Event::Base::TextMessage $event
     *      Contents of the help request (eg. name of a module
     *      or command).
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public function handleHelp(
        \Erebot\Interfaces\EventHandler $handler,
        \Erebot\Interfaces\Event\Base\TextMessage $event
    ) {
        if ($event instanceof \Erebot\Interfaces\Event\Base\PrivateMessage) {
            $target = $event->getSource();
            $chan   = null;
        } else {
            $target = $chan = $event->getChan();
        }

        $fmt        = $this->getFormatter($chan);
        $wrapperCls = $this->getFactory('!TextWrapper');
        $text       = $event->getText()->getTokens(1); // shift "!help" trigger.

        // Just "!help". Emulate "!help \Erebot\Module\Helper".
        if ($text == "") {
            $text = new $wrapperCls(get_called_class());
        } else {
            $text = new $wrapperCls($text);
        }

        // Got a request on a module, check if it exists/has a callback.
        $moduleName = self::getModuleName($text);
        if ($moduleName === null) {
            $moduleNames = $this->getModules($chan);
        } elseif (!$this->checkCallback($fmt, $target, $chan, $moduleName)) {
            return;
        } else {
            $moduleNames = array($moduleName);
        }

        // Now, use the appropriate callback to handle the request.
        // If the request directly concerns a command (!help command),
        // loop through all callbacks until one handles the request.
        $words = ' '.(string) $text;
        if ($words === ' ') {
            $words = '';
        }
        foreach ($moduleNames as $modName) {
            if (!isset($this->helpCallbacks[$modName])) {
                continue;
            }
            $callback = $this->helpCallbacks[$modName];
            if ($callback($event, new $wrapperCls('Erebot\\Module\\' . $modName . $words))) {
                return;
            }
            // Fallback to unprefixed module name.
            if ($callback($event, new $wrapperCls($modName . $words))) {
                return;
            }
        }

        if ($moduleName !== null) {
            $msg = $fmt->_(
                'No help available on module <b><var name="module"/></b>.',
                array('module' => $moduleName)
            );
            return $this->sendMessage($target, $msg);
        }

        // No callback handled this request.
        // We assume no help is available.
        $msg = $fmt->_(
            'No help available on command <b><var name="command"/></b>.',
            array('command' => $event->getText()->getTokens(1))
        );
        $this->sendMessage($target, $msg);
    }

    protected function getModules($chan)
    {
        // Get modules and normalize them.
        $modules = array_keys($this->connection->getModules($chan));
        $modules = array_map(array(get_called_class(), 'normalizeModule'), $modules);
        sort($modules);
        return $modules;
    }

    public static function normalizeModule($moduleName)
    {
        // Remove "\Erebot\Module\" or "\" prefix if present,
        // and lowercase the module name.
        $moduleName = ltrim($moduleName, '\\');
        if (!strncasecmp($moduleName, 'Erebot\\Module\\', 14)) {
            $moduleName = (string) substr($moduleName, 14);
        }
        return $moduleName;
    }
}
