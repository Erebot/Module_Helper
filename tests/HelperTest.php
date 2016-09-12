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

namespace Erebot\Module {
    abstract class TestModule extends \Erebot\Module\Base
    {
    }

    abstract class TestModule2 extends \Erebot\Module\Base
    {
    }
}

namespace {
abstract class  TextWrapper
implements      \Erebot\Interfaces\TextWrapper
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

    public function offsetExists($offset)
    {
        return isset($this->_chunks[$offset]);
    }

    public function count()
    {
        return count($this->_chunks);
    }
}

class   HelperTest
extends Erebot_Testenv_Module_TestCase
{
    public function setUp()
    {
        $this->_module = new \Erebot\Module\Helper(NULL);

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

        $this->_module->reloadModule(
            $this->_connection,
            \Erebot\Module\Base::RELOAD_MEMBERS
        );

        // Create two fake modules for the tests.
        $this->_fakeModules = array(
            $this->getMockForAbstractClass(
                '\\Erebot\\Module\\TestModule',
                array(),
                '',
                FALSE,
                FALSE
            ),
            $this->getMockForAbstractClass(
                '\\Erebot\\Module\\TestModule2',
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
                        '\\Erebot\\Module\\Helper' => $this->_module,
                        get_class($this->_fakeModules[0]) => $this->_fakeModules[0],
                        get_class($this->_fakeModules[1]) => $this->_fakeModules[1],
                    )
                )
            );

        $this->_connection
            ->expects($this->any())
            ->method('getModule')
            ->will($this->returnValue($this->_module));

        // Register some method to return help for the first fake module.
        $this->_module->realRegisterHelpMethod(
            $this->_fakeModules[0],
            \Erebot\CallableWrapper::wrap(array($this, 'getFakeHelp'))
        );
    }

    public function tearDown()
    {
        $this->_module->unloadModule();
        parent::tearDown();
    }

    public function getFakeHelp(
        \Erebot\Interfaces\Event\Base\TextMessage $event,
        \Erebot\Interfaces\TextWrapper            $words
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
        $event = $this->getMockBuilder('\\Erebot\\Interfaces\\Event\\PrivateText')->getMock();

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
            "PRIVMSG foo :\002Usage\002: \"!help <\037Module\037> [\037command\037]\" ".
            "or \"!help <\037command\037>\". Provides help about a particular ".
            'module or command. Module names must start with an uppercase letter. '.
            "The following modules are currently loaded: \002Helper\002, ".
            "\002".get_class($this->_fakeModules[1])."\002 & ".
            "\002".get_class($this->_fakeModules[0])."\002.",
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
            "PRIVMSG foo :No help available on command \002does_not_exist\002.",
            $this->_outputBuffer[0]
        );
    }

    public function testNoHelpForModule()
    {
        $event = $this->_getEvent("!help ".get_class($this->_fakeModules[1]));
        $this->_module->handleHelp($this->_eventHandler, $event);
        $this->assertSame(1, count($this->_outputBuffer));
        $this->assertSame(
            "PRIVMSG foo :No help available on module \002".
                get_class($this->_fakeModules[1])."\002.",
            $this->_outputBuffer[0]
        );
    }

    public function testUnknownModule()
    {
        $event = $this->_getEvent("!help DoesNotExist");
        $this->_module->handleHelp($this->_eventHandler, $event);
        $this->assertSame(1, count($this->_outputBuffer));
        $this->assertSame(
            "PRIVMSG foo :No such module \002DoesNotExist\002. ".
            "Available modules: \002Helper\002, ".
            "\002".get_class($this->_fakeModules[1])."\002 & ".
            "\002".get_class($this->_fakeModules[0])."\002.",
            $this->_outputBuffer[0]
        );
    }
}
} // Namespace
