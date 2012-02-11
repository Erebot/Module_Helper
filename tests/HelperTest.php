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

abstract class  TextWrapper
implements      Erebot_Interface_TextWrapper
{
    private $_chunks;

    public function __construct($text)
    {
        $this->_chunks = explode(' ', $text);
    }

    public function __toString()
    {
        return implode(' ', $this->_chunks);
    }

    public function getTokens($start, $length = 0, $separator = " ")
    {
        return implode(" ", array_slice($this->_chunks, $start));
    }

    public function offsetGet($offset)
    {
        return $this->_chunks[$offset];
    }

    public function count()
    {
        return count($this->_chunks);
    }
}

abstract class  TestModule
extends         Erebot_Module_Base
{
}

abstract class  TestModule2
extends         Erebot_Module_Base
{
}

class   HelperTest
extends Erebot_Testenv_Module_TestCase
{
    public function setUp()
    {
        $this->_module = new Erebot_Module_Helper(NULL);

        $mock = $this->getMockForAbstractClass(
                'TextWrapper',
                array(),
                '',
                FALSE,
                FALSE
            );
        // Override default factories in test & module.
        $this->_factory['!TextWrapper'] = get_class($mock);
        $this->_module->setFactory('!TextWrapper', get_class($mock));

        parent::setUp();
        $this->_serverConfig
            ->expects($this->any())
            ->method('parseString')
            ->will($this->returnValue("help"));

        $this->_module->reload(
            $this->_connection,
            Erebot_Module_Base::RELOAD_MEMBERS
        );

        // Create two fake modules for the tests.
        $this->_fakeModules = array(
            $this->getMockForAbstractClass(
                'TestModule',
                array(),
                '',
                FALSE,
                FALSE
            ),
            $this->getMockForAbstractClass(
                'TestModule2',
                array(),
                '',
                FALSE,
                FALSE
            ),
        );

        // Emulates 2 active modules (Helper + Test) for this connection.
        $this->_connection
            ->expects($this->any())
            ->method('getModules')
            ->will(
                $this->returnValue(
                    array(
                        'Erebot_Module_Helper' => $this->_module,
                        get_class($this->_fakeModules[0]) =>
                            $this->_fakeModules[0],
                        get_class($this->_fakeModules[1]) =>
                            $this->_fakeModules[1],
                    )
                )
            );

        $this->_connection
            ->expects($this->any())
            ->method('getModule')
            ->will($this->returnValue($this->_module));

        // Register some method to return help for the first fake module.
        $cls = $this->_factory['!Callable'];
        $this->_module->realRegisterHelpMethod(
            $this->_fakeModules[0],
            new $cls(array($this, 'getFakeHelp'))
        );
    }

    public function tearDown()
    {
        $this->_module->unload();
        parent::tearDown();
    }

    public function getFakeHelp(
        Erebot_Interface_Event_Base_TextMessage $event,
        Erebot_Interface_TextWrapper            $words
    )
    {
        if (count($words) == 1) {
            $this->_outputBuffer[] =
                "PRIVMSG ".$event->getSource().' :Help on fake module';
            return TRUE;
        }

        if ($words[1] == "fake") {
            $this->_outputBuffer[] =
                "PRIVMSG ".$event->getSource().' :Help on fake command';
            return TRUE;
        }

        return FALSE;
    }

    protected function _getEvent($text)
    {
        $event = $this->getMock(
            'Erebot_Interface_Event_PrivateText',
            array(), array(), '', FALSE, FALSE
        );

        $wrapperCls = $this->_factory['!TextWrapper'];
        $wrapped    = new $wrapperCls($text);

        $event
            ->expects($this->any())
            ->method('getConnection')
            ->will($this->returnValue($this->_connection));
        $event
            ->expects($this->any())
            ->method('getSource')
            ->will($this->returnValue('foo'));
        $event
            ->expects($this->any())
            ->method('getText')
            ->will($this->returnValue($wrapped));

        return $event;
    }

    public function testHelpForHelp()
    {
        $event = $this->_getEvent("!help");
        $this->_module->handleHelp($this->_eventHandler, $event);
        $this->assertSame(1, count($this->_outputBuffer));
        $this->assertSame(
            'PRIVMSG foo :<b>Usage</b>: "!<var value="help"/> '.
            '&lt;<u>Module</u>&gt; [<u>command</u>]" or "!<var value="help"/> '.
            '&lt;<u>command</u>&gt;". Provides help about a particular '.
            'module or command. Use "!<var value="help"/> '.
            '<var value="Erebot_Module_Helper"/>" for a list of currently '.
            'loaded modules.',
            $this->_outputBuffer[0]
        );
    }

    public function testHelpForHelperModule()
    {
        $event = $this->_getEvent("!help Erebot_Module_Helper");
        $this->_module->handleHelp($this->_eventHandler, $event);
        $this->assertSame(1, count($this->_outputBuffer));
        $modules = array(
            'Erebot_Module_Helper',
            get_class($this->_fakeModules[0]),
            get_class($this->_fakeModules[1]),
        );
        $modules = preg_replace('/\\s+/', ' ', var_export($modules, TRUE));
        $this->assertSame(
            'PRIVMSG foo :<b>Usage</b>: "!<var value="help"/> '.
            '&lt;<u>Module</u>&gt; [<u>command</u>]". Module names must '.
            'start with an uppercase letter but are not case-sensitive '.
            'otherwise. The following modules are loaded: <for from="'.
            $modules.'" item="module"><b><var name="module"/></b></for>.',
            $this->_outputBuffer[0]
        );
    }

    public function testHelpForCommand()
    {
        $event = $this->_getEvent("!help fake");
        $this->_module->handleHelp($this->_eventHandler, $event);
        $this->assertSame(1, count($this->_outputBuffer));
        $this->assertSame(
            'PRIVMSG foo :Help on fake command',
            $this->_outputBuffer[0]
        );
    }

    public function testHelpForModule()
    {
        $event = $this->_getEvent("!help ".get_class($this->_fakeModules[0]));
        $this->_module->handleHelp($this->_eventHandler, $event);
        $this->assertSame(1, count($this->_outputBuffer));
        $this->assertSame(
            'PRIVMSG foo :Help on fake module',
            $this->_outputBuffer[0]
        );
    }

    public function testNoHelpForCommand()
    {
        $event = $this->_getEvent("!help does_not_exist");
        $this->_module->handleHelp($this->_eventHandler, $event);
        $this->assertSame(1, count($this->_outputBuffer));
        $this->assertSame(
            'PRIVMSG foo :No help available on command <b><var '.
                'value="does_not_exist"/></b>.',
            $this->_outputBuffer[0]
        );
    }

    public function testNoHelpForModule()
    {
        $event = $this->_getEvent("!help ".get_class($this->_fakeModules[1]));
        $this->_module->handleHelp($this->_eventHandler, $event);
        $this->assertSame(1, count($this->_outputBuffer));
        $this->assertSame(
            'PRIVMSG foo :No help available on module <b><var value="'.
                get_class($this->_fakeModules[1]).'"/></b>.',
            $this->_outputBuffer[0]
        );
    }

    public function testUnknownModule()
    {
        $event = $this->_getEvent("!help DoesNotExist");
        $this->_module->handleHelp($this->_eventHandler, $event);
        $this->assertSame(1, count($this->_outputBuffer));
        $this->assertSame(
            'PRIVMSG foo :No such module <b><var value="DoesNotExist"/></b>.',
            $this->_outputBuffer[0]
        );
    }
}

