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

class   Erebot_Module_Helper
extends Erebot_Module_Base
{
    static protected $_metadata = array(
        'requires'  =>  array('TriggerRegistry'),
    );
    protected $_trigger;
    protected $_handler;
    protected $_helpTopics;
    protected $_helpCallbacks;

    public function reload($flags)
    {
        if (!($flags & self::RELOAD_INIT)) {
            $registry =& $this->_connection->getModule(
                'Erebot_Module_TriggerRegistry'
            );
            $matchAny = Erebot_Utils::getVStatic($registry, 'MATCH_ANY');

            $this->_connection->removeEventHandler($this->_handler);
            $registry->freeTriggers($this->_trigger, $matchAny);
        }

        if ($flags & self::RELOAD_HANDLERS) {
            $registry =& $this->_connection->getModule(
                'Erebot_Module_TriggerRegistry'
            );
            $matchAny  =   Erebot_Utils::getVStatic($registry, 'MATCH_ANY');

            $trigger        = $this->parseString('trigger', 'help');
            $this->_trigger = $registry->registerTriggers($trigger, $matchAny);
            if ($this->_trigger === NULL)
                throw new Exception($this->_translator->gettext(
                    'Could not register Help trigger'));

            $filter         = new Erebot_TextFilter($this->_mainCfg);
            $filter->addPattern(Erebot_TextFilter::TYPE_STATIC, $trigger, TRUE);
            $filter->addPattern(Erebot_TextFilter::TYPE_WILDCARD, $trigger.' *', TRUE);
            $this->_handler  = new Erebot_EventHandler(
                                    array($this, 'handleHelp'),
                                    'Erebot_Interface_Event_TextMessage',
                                    NULL, $filter);
            $this->_connection->addEventHandler($this->_handler);

            // Add help support to Help module.
            // This has to be done by hand, because the module
            // is not registered for this connection yet.
            $this->realRegisterHelpMethod($this, array($this, 'getHelp'));
        }
    }

    public function realRegisterHelpMethod(Erebot_Module_Base &$module, $callback)
    {
        $bot =& $this->_connection->getBot();
        $moduleName = strtolower(get_class($module));
        $reflector  = new ReflectionParameter($callback, 0);
        $cls        = $reflector->getClass();
        if ($cls === NULL || !$cls->implementsInterface('Erebot_Interface_Event_MessageCapable'))
            throw new Erebot_InvalidValueException('Invalid signature');

        $this->_helpCallbacks[$moduleName] =& $callback;
        return TRUE;
    }

    public function getHelp(Erebot_Interface_Event_TextMessage &$event, $words)
    {
        if ($event instanceof Erebot_Interface_Event_Private) {
            $target = $event->getSource();
            $chan   = NULL;
        }
        else
            $target = $chan = $event->getChan();

        $translator = $this->getTranslator($chan);
        $trigger    = $this->parseString('trigger', 'help');

        $bot        =&  $this->_connection->getBot();
        $moduleName =   strtolower(get_class());
        $nbArgs     =   count($words);

        // "!help Helper"
        if ($nbArgs == 1 && $words[0] == $moduleName) {
            $modules = array_keys($this->_connection->getModules($chan));
            $msg = $translator->gettext('
<b>Usage</b>: "!<var name="trigger"/> &lt;<u>Module</u>&gt; [<u>command</u>]".
Module names must start with an uppercase letter but are not case-sensitive
otherwise.
The following modules are loaded: <for from="modules" item="module">
<b><var name="module"/></b></for>.
');
            $tpl = new Erebot_Styling($msg, $translator);
            $tpl->assign('modules', $modules);
            $tpl->assign('trigger', $trigger);
            $this->sendMessage($target, $tpl->render());
            return TRUE;
        }

        if ($nbArgs < 2 || $words[1] != $trigger)
            return FALSE;

        // "!help Helper *" or just "!help"
        $msg = $translator->gettext('
<b>Usage</b>: "!<var name="trigger"/> &lt;<u>Module</u>&gt; [<u>command</u>]"
or "!<var name="trigger"/> &lt;<u>command</u>&gt;".
Provides help about a particular module or command.
Use "!<var name="trigger"/> <var name="this"/>" for a list of currently loaded
modules.
');
        $bot =& $this->_connection->getBot();
        $tpl = new Erebot_Styling($msg, $translator);
        $tpl->assign('this',    get_class());
        $tpl->assign('trigger', $trigger);
        $this->sendMessage($target, $tpl->render());
        return TRUE;
    }

    public function handleHelp(Erebot_Interface_Event_TextMessage &$event)
    {
        if ($event instanceof Erebot_Interface_Event_Private) {
            $target = $event->getSource();
            $chan   = NULL;
        }
        else
            $target = $chan = $event->getChan();

        $text       = preg_split('/\s+/', rtrim($event->getText()));
        $foo        = array_shift($text); // Consume '!help' trigger.
        $translator = $this->getTranslator($chan);

        // Just "!help". Emulate "!help Help help".
        if (!count($text)) {
            $bot =& $this->_connection->getBot();
            $text = array(get_class(), 'help');
        }

        $moduleName = NULL;
        // If the first letter of the first word is in uppercase,
        // this is a request for help on a module (!help Module).
        $first = substr($text[0][0], 0, 1);
        if ($first == strtoupper($first))
            $moduleName = strtolower(array_shift($text));

        // Got request on a module, check if it exists/has a callback.
        if ($moduleName !== NULL &&
            !isset($this->_helpCallbacks[$moduleName])) {
            $msg = $translator->gettext(
                'No such module <b><var name="module"/></b> '.
                'or no help available.');
            $tpl = new Erebot_Styling($msg, $translator);
            $tpl->assign('module', $moduleName);
            return $this->sendMessage($target, $tpl->render());
        }

        // Now, use the appropriate callback to handle the request.
        // If the request directly concerns a command (!help command),
        // loop through all callbacks until one handles the request.
        if ($moduleName === NULL)
            $moduleNames = array_map('strtolower', array_keys(
                            $this->_connection->getModules($chan)));
        else
            $moduleNames = array($moduleName);

        foreach ($moduleNames as $modName) {
            if (!isset($this->_helpCallbacks[$modName]))
                continue;
            $callback = $this->_helpCallbacks[$modName];
            $words = $text;
            array_unshift($words, $moduleName);
            if (call_user_func($callback, $event, $words))
                return;
        }

        // No callback handled this request.
        // We assume no help is available.
        $msg = $translator->gettext('No help available on the given '.
                                    'module or command.');
        $tpl = new Erebot_Styling($msg, $translator);
        $this->sendMessage($target, $tpl->render());
    }
}
