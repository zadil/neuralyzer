<?php

namespace Edyan\Neuralyzer\Tests;

use Edyan\Neuralyzer\Anonymizer\DB;
use Edyan\Neuralyzer\Configuration\Writer;
use Edyan\Neuralyzer\Configuration\Reader;
use Edyan\Neuralyzer\Guesser;

class ConfigWriterTest extends ConfigurationDB
{
    private $protectedCols = ['.*\..*'];
    private $ignoredTables = ['guestbook'];

    /**
     * @expectedException Edyan\Neuralyzer\Exception\NeuralizerConfigurationException
     * @expectedExceptionMessageRegExp |Can't work with guestbook, it has no primary key.|
     */
    public function testGenerateConfNoPrimary()
    {
        $writer = new Writer;
        $writer->generateConfFromDB(new Db($this->getDbParams()), new Guesser);
    }

    /**
     * @expectedException Edyan\Neuralyzer\Exception\NeuralizerConfigurationException
     * @expectedExceptionMessageRegExp |No tables to read in that database|
     */
    public function testGenerateConfNoTable()
    {
        $this->dropTable();

        $writer = new Writer;
        $writer->generateConfFromDB(new Db($this->getDbParams()), new Guesser);
    }

    /**
     * @expectedException Edyan\Neuralyzer\Exception\NeuralizerConfigurationException
     * @expectedExceptionMessageRegExp |All tables or fields have been ignored|
     */
    public function testGenerateConfIgnoreAllFields()
    {
        $this->createPrimary();

        $writer = new Writer;
        $writer->setProtectedCols($this->protectedCols);
        $writer->generateConfFromDB(new Db($this->getDbParams()), new Guesser);
    }

    /**
     * @expectedException Edyan\Neuralyzer\Exception\NeuralizerConfigurationException
     * @expectedExceptionMessageRegExp |No tables to read in that database|
     */
    public function testGenerateConfIgnoreAllTables()
    {
        $this->createPrimary();

        $writer = new Writer;
        $writer->setIgnoredTables($this->ignoredTables);
        $writer->generateConfFromDB(new Db($this->getDbParams()), new Guesser);
    }

    public function testGenerateConfDontIgnore()
    {
        $this->createPrimary();

        $writer = new Writer;
        $writer->setProtectedCols(['.*\..*']);
        $writer->protectCols(false);
        $entities = $writer->generateConfFromDB(new Db($this->getDbParams()), new Guesser);
        $this->assertInternalType('array', $entities);
        $this->assertArrayHasKey('entities', $entities);
        $this->assertArrayHasKey('guestbook', $entities['entities']);

        $guestbook = $entities['entities']['guestbook'];
        $this->assertArrayHasKey('cols', $guestbook);
        $this->assertArrayNotHasKey('id', $guestbook['cols']);

        // check field by field
        $fields = [
            'content', 'username', 'created', 'a_bigint', 'a_datetime', 'a_time', 'a_decimal',
            'an_integer', 'a_smallint', 'a_float'
        ];
        foreach ($fields as $field) {
            $this->assertArrayHasKey('content', $guestbook['cols']);
            $this->assertArrayHasKey('method', $guestbook['cols'][$field]);
            $this->assertArrayHasKey('params', $guestbook['cols'][$field]);
        }
        $this->assertEquals('sentence', $guestbook['cols']['content']['method']);
        $this->assertEquals('sentence', $guestbook['cols']['username']['method']);
        $this->assertEquals('date', $guestbook['cols']['created']['method']);
        $this->assertEquals('date', $guestbook['cols']['a_datetime']['method']);
        $this->assertEquals('randomNumber', $guestbook['cols']['a_bigint']['method']);
        $this->assertEquals('time', $guestbook['cols']['a_time']['method']);
        $this->assertEquals('randomFloat', $guestbook['cols']['a_decimal']['method']);
        $this->assertEquals('randomNumber', $guestbook['cols']['an_integer']['method']);
        $this->assertEquals('randomNumber', $guestbook['cols']['a_smallint']['method']);
        $this->assertEquals('randomFloat', $guestbook['cols']['a_float']['method']);

        $tablesInConf = $writer->getTablesInConf();
        $this->assertInternalType('array', $tablesInConf);
        $this->assertContains('guestbook', $tablesInConf);
    }

    public function testGenerateConfWriteable()
    {
        $this->createPrimary();

        $writer = new Writer;
        $writer->setProtectedCols(['.*\.username']);
        $writer->protectCols(true);
        $entities = $writer->generateConfFromDB(new Db($this->getDbParams()), new Guesser);
        $this->assertInternalType('array', $entities);
        $this->assertArrayHasKey('entities', $entities);
        $this->assertArrayHasKey('guestbook', $entities['entities']);
        $this->assertArrayHasKey('cols', $entities['entities']['guestbook']);
        $this->assertArrayNotHasKey('id', $entities['entities']['guestbook']['cols']);
        $this->assertArrayNotHasKey('username', $entities['entities']['guestbook']['cols']);
        $this->assertArrayHasKey('content', $entities['entities']['guestbook']['cols']);

        // check the configuration for the date
        $created = $entities['entities']['guestbook']['cols']['created'];
        $this->assertInternalType('array', $created);
        $this->assertArrayHasKey('method', $created);
        $this->assertArrayHasKey('params', $created);
        $this->assertEquals('date', $created['method']);
        $this->assertEquals('Y-m-d', $created['params'][0]);

        // save it
        $temp = tempnam(sys_get_temp_dir(), 'phpunit');
        $writer->save($entities, $temp);
        $this->assertFileExists($temp);

        // try to read the file with the reader
        $reader = new Reader($temp);
        $values = $reader->getConfigValues();
        $this->assertInternalType('array', $values);
        $this->assertArrayHasKey('entities', $values);

        // delete it
        unlink($temp);
    }

    /**
     * @expectedException Edyan\Neuralyzer\Exception\NeuralizerConfigurationException
     * @expectedExceptionMessageRegExp |/doesntexist is not writeable.|
     */
    public function testGenerateConfNotWriteable()
    {
        $this->createPrimary();

        $writer = new Writer;
        $writer->setProtectedCols(['.*\.username']);
        $writer->protectCols(true);
        $entities = $writer->generateConfFromDB(new Db($this->getDbParams()), new Guesser);
        // save it
        $writer->save($entities, '/doesntexist/toto');
    }
}
