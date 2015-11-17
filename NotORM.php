<?php

namespace Panada\Notorm;

/** NotORM - simple reading data from the database
 * @link http://www.notorm.com/
 *
 * @author Jakub Vrana, http://www.vrana.cz/
 * @copyright 2010 Jakub Vrana
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
 */
include_once dirname(__FILE__).'/NotORM/Structure.php';
include_once dirname(__FILE__).'/NotORM/Cache.php';
include_once dirname(__FILE__).'/NotORM/Literal.php';
include_once dirname(__FILE__).'/NotORM/Result.php';
include_once dirname(__FILE__).'/NotORM/MultiResult.php';
include_once dirname(__FILE__).'/NotORM/Row.php';

// friend visibility emulation
abstract class NotORMAbstract
{
    protected $connection, $driver, $structure, $cache;
    protected $notORM, $table, $primary, $rows, $referenced = array();

    protected $debug = false;
    protected $debugTimer;
    protected $freeze = false;
    protected $rowClass = 'Panada\Notorm\NotORMRow';
    protected $jsonAsArray = false;

    protected function access($key, $delete = false)
    {
    }
}

/** Database representation
 * @property-write mixed $debug = false Enable debugging queries, true for error_log($query), callback($query, $parameters) otherwise
 * @property-write bool $freeze = false Disable persistence
 * @property-write string $rowClass = 'NotORMRow' Class used for created objects
 * @property-write bool $jsonAsArray = false Use array instead of object in Result JSON serialization
 * @property-write string $transaction Assign 'BEGIN', 'COMMIT' or 'ROLLBACK' to start or stop transaction
 */
class NotORM extends NotORMAbstract
{
    protected static $instance = [];

    /** Create database representation
     * @param PDO
     * @param NotORMStructure or null for new NotORMStructureConvention
     * @param NotORMCache or null for no cache
     */
    public function __construct(\PDO $connection, NotORMStructure $structure = null, NotORMCache $cache = null)
    {
        $this->connection = $connection;
        $this->driver = $connection->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if (!isset($structure)) {
            $structure = new NotORMStructureConvention();
        }
        $this->structure = $structure;
        $this->cache = $cache;
    }

    public static function getInstance($type = 'default')
    {
        return new static(\Panada\Database\SQL::getInstance($type)->connect());
    }

    /** Get table data to use as $db->table[1]
     * @param string
     *
     * @return NotORMResult
     */
    public function __get($table)
    {
        return new NotORMResult($this->structure->getReferencingTable($table, ''), $this, true);
    }

    /** Set write-only properties
     */
    public function __set($name, $value)
    {
        if ($name == 'debug' || $name == 'debugTimer' || $name == 'freeze' || $name == 'rowClass' || $name == 'jsonAsArray') {
            $this->$name = $value;
        }
        if ($name == 'transaction') {
            switch (strtoupper($value)) {
                case 'BEGIN': return $this->connection->beginTransaction();
                case 'COMMIT': return $this->connection->commit();
                case 'ROLLBACK': return $this->connection->rollback();
            }
        }
    }

    /** Get table data
     * @param string
     * @param array (["condition"[, array("value")]]) passed to NotORMResult::where()
     *
     * @return NotORMResult
     */
    public function __call($table, array $where)
    {
        $return = new NotORMResult($this->structure->getReferencingTable($table, ''), $this);
        if ($where) {
            call_user_func_array(array($return, 'where'), $where);
        }

        return $return;
    }
    
    public function getConnection()
    {
        return $this->connection;
    }
}
