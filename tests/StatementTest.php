<?php

require_once __DIR__.'/BaseTest.php';

use FSQL\Database\CachedTable;
use FSQL\Environment;
use FSQL\Statement;
use FSQL\ResultSet;

class StatementTest extends BaseTest
{
    private $fsql;

    private static $columns = [
        'personId' => ['type' => 'i', 'auto' => 0, 'default' => 0, 'key' => 'n', 'null' => 1, 'restraint' => []],
        'firstName' => ['type' => 's', 'auto' => 0, 'default' => '', 'key' => 'n', 'null' => 1, 'restraint' => []],
        'lastName' => ['type' => 's', 'auto' => 0, 'default' => '', 'key' => 'n', 'null' => 1, 'restraint' => []],
        'city' => ['type' => 's', 'auto' => 0, 'default' => '', 'key' => 'n', 'null' => 1, 'restraint' => []],
        'zip' => ['type' => 'f', 'auto' => 0, 'default' => 0.0, 'key' => 'n', 'null' => 1, 'restraint' => []],
    ];

    private static $entries = [
        [1, 'bill', 'smith', 'chicago', 12345],
        [2, 'jon', 'doe', 'baltimore', 54321],
        [3, 'mary', 'shelley', 'seattle', 98765],
        [4, 'stephen', 'king', 'derry', 42424],
        [5, 'bart', 'simpson', 'springfield', 55555],
        [6, 'jane', 'doe', 'seattle', 98765],
        [7, 'bram', 'stoker', 'new york', 56789],
        [8, 'douglas', 'adams', 'london', 99999],
        [9, 'bill', 'johnson', 'derry', 42424],
        [10, 'jon', 'doe', 'new york', 56789],
        [11, 'homer', null, 'boston', 22222],
        [12, null, 'king', 'tokyo', 11111],
    ];

    public function setUp()
    {
        parent::setUp();
        $this->fsql = new Environment();
        $this->fsql->define_db('db1', parent::$tempDir);
        $this->fsql->select_db('db1');
    }

    public function testPrepare()
    {
        $statement = new Statement($this->fsql);
        $passed = $statement->prepare("SELECT firstName, lastName, city FROM customers WHERE personId = ? OR lastName = ? OR zip = ?");
        $this->assertTrue($passed === true);
    }

    public function testBindParamNoPrepare()
    {
        $statement = new Statement($this->fsql);
        $passed = $statement->bind_param('isd', '5', 'king', 99999);
        $this->assertTrue($passed === false);
        $this->assertEquals($statement->error(), "Unable to perform a bind_param without a prepare");
    }

    public function testBindParamTypeParamMismatch()
    {
        $statement = new Statement($this->fsql);
        $statement->prepare("SELECT firstName, lastName, city FROM customers WHERE personId = ? OR lastName = ? OR zip = ?");
        $passed = $statement->bind_param('isd', '5', 'king');
        $this->assertTrue($passed === false);
        $this->assertEquals($statement->error(), "bind_param's number of types in the string doesn't match number of parameters passed in");
    }

    public function testBindParam()
    {
        $statement = new Statement($this->fsql);
        $statement->prepare("SELECT firstName, lastName, city FROM customers WHERE personId = ? OR lastName = ? OR zip = ?");
        $passed = $statement->bind_param('isd', '5', 'king', 99999);
        $this->assertTrue($passed === true);
    }

    public function testExecuteNoPrepare()
    {
        $statement = new Statement($this->fsql);
        $passed = $statement->execute();
        $this->assertTrue($passed === false);
        $this->assertEquals($statement->error(), "Unable to perform an execute without a prepare");
    }

    public function testExecuteNoParams()
    {
        $table = CachedTable::create($this->fsql->current_schema(), 'customers', self::$columns);
        $cursor = $table->getWriteCursor();
        foreach (self::$entries as $entry) {
            $cursor->appendRow($entry);
        }
        $table->commit();

        $statement = new Statement($this->fsql);
        $statement->prepare("SELECT firstName, lastName, city FROM customers WHERE personId = 5");
        $passed = $statement->execute();
        $this->assertTrue($passed === true);
    }

    public function testExecuteParams()
    {
        $table = CachedTable::create($this->fsql->current_schema(), 'customers', self::$columns);
        $cursor = $table->getWriteCursor();
        foreach (self::$entries as $entry) {
            $cursor->appendRow($entry);
        }
        $table->commit();

        $statement = new Statement($this->fsql);
        $statement->prepare("SELECT firstName, lastName, city FROM customers WHERE personId = ? OR lastName = ? OR zip = ?");
        $statement->bind_param('isd', '5', 'king', 99999);
        $passed = $statement->execute();
        $this->assertTrue($passed === true);
    }

    public function testStoreResultNoPrepare()
    {
        $statement = new Statement($this->fsql);
        $passed = $statement->store_result();
        $this->assertTrue($passed === false);
        $this->assertEquals($statement->error(), "Unable to perform a store_result without a prepare");
    }

    public function testStoreResult()
    {
        $table = CachedTable::create($this->fsql->current_schema(), 'customers', self::$columns);
        $cursor = $table->getWriteCursor();
        foreach (self::$entries as $entry) {
            $cursor->appendRow($entry);
        }
        $table->commit();

        $statement = new Statement($this->fsql);
        $statement->prepare("SELECT firstName, lastName, city FROM customers WHERE personId = ? OR lastName = ? OR zip = ?");
        $statement->bind_param('isd', '5', 'king', null);
        $statement->execute();
        $passed = $statement->store_result();
        $this->assertTrue($passed === true);
    }

    public function testGetResultNoPrepare()
    {
        $statement = new Statement($this->fsql);
        $passed = $statement->get_result();
        $this->assertTrue($passed === false);
        $this->assertEquals($statement->error(), "Unable to perform a get_result without a prepare");
    }

    public function testGetResult()
    {
        $table = CachedTable::create($this->fsql->current_schema(), 'customers', self::$columns);
        $cursor = $table->getWriteCursor();
        foreach (self::$entries as $entry) {
            $cursor->appendRow($entry);
        }
        $table->commit();

        $statement = new Statement($this->fsql);
        $statement->prepare("SELECT firstName, lastName, city FROM customers WHERE personId = 5");
        $statement->execute();
        $result = $statement->get_result();
        $this->assertTrue($result instanceof ResultSet);
    }

    // public function testEnvPrepare()
    // {
    //     $dbName = 'db1';
    //     $passed = $this->fsql->define_db($dbName, parent::$tempDir);
    //     $this->fsql->select_db($dbName);
    //
    //     $table = CachedTable::create($this->fsql->current_schema(), 'customers', self::$columns);
    //     $cursor = $table->getWriteCursor();
    //     foreach (self::$entries as $entry) {
    //         $cursor->appendRow($entry);
    //     }
    //     $table->commit();
    //
    //     $expected = [
    //         ['stephen', 'king', 'derry'],
    //         ['bart', 'simpson', 'springfield'],
    //         [null, 'king', 'tokyo'],
    //     ];
    //
    //     $stmt = $this->fsql->prepare("SELECT firstName, lastName, city FROM customers WHERE personId = ? OR lastName = ? OR zip = ?");
    //     $this->assertTrue($passed !== false);
    //     $stmt->bind_param('is', '5', 'king');
    //     $passed = $stmt->execute();
    //     $this->assertTrue($passed !== false);
    //     $result = $stmt->get_result();
    //
    //     $results = $this->fsql->fetch_all($result, ResultSet::FETCH_NUM);
    //     $this->assertEquals($expected, $results);
    // }
    //
    // public function testPrepareInject()
    // {
    //     $dbName = 'db1';
    //     $passed = $this->fsql->define_db($dbName, parent::$tempDir);
    //     $this->fsql->select_db($dbName);
    //
    //     $table = CachedTable::create($this->fsql->current_schema(), 'customers', self::$columns);
    //     $cursor = $table->getWriteCursor();
    //     foreach (self::$entries as $entry) {
    //         $cursor->appendRow($entry);
    //     }
    //     $table->commit();
    //
    //     $stmt = $this->fsql->prepare("SELECT firstName, lastName, city FROM customers WHERE lastName = ?");
    //     $this->assertTrue($stmt !== false);
    //     $stmt->bind_param('s', 'doe;delete from customers');
    //     $passed = $stmt->execute();
    //     $this->assertTrue($passed !== false);
    //     $result = $stmt->get_result();
    //
    //     $results = $this->fsql->fetch_all($result, ResultSet::FETCH_NUM);
    //     $this->assertSame([], $results);
    // }
}