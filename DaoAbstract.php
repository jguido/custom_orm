<?php

/**
 * Base class for the data access object entities
 *
 */
abstract class DaoAbstract {

    /**
     * The name of the database table name
     *
     * @var string
     */
    protected $tableName;

    /**
     * The alias of the table
     *
     * @var string
     */
    protected $alias;

    /**
     * The primary key name of the table
     *
     * @var string
     */
    protected $primaryKey;

    /**
     * The fields name of the table
     *
     * @var array
     */
    protected $fields;

    /**
     * The database singleton connexion
     *
     * @var \PDO
     */
    protected $connexion;

    /**
     * The entity mapped by the class
     *
     * @var object
     */
    protected $objectInstance;

    /**
     * The links between an entity to an other
     * example : 
     * array('table' => 'ger_operation', 'field' => 'id_programme', 'type' => 'manyToOne', 'property' => $operations)
     *
     * @var array
     */
    protected $links;

    /**
     * Run the query with the parameters and return result set
     * 
     * @param string $query The query
     * @param array  $queryDatas the array of key and value for bindValue
     * 
     * @return array | object
     * 
     * @throws Exception On every exception raise the exception
     */
    final protected function runQuery($query, $queryDatas) {
        try {
            $statement = $this->connexion->prepare($query);
            if ($queryDatas) {
                foreach ($queryDatas as $key => $value) {
                    $statement->bindValue(':' . $key, $value);
                }
            }
            $statement->execute();
            if ($statement->rowCount() > 1) {
                $results = array();
                $rowsSet = $statement->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($rowsSet as $rowSet) {
                    array_push($results, $this->instanciate($rowSet));
                }

                return $results;
            } else {

                return $this->instanciate($statement->fetch(\PDO::FETCH_ASSOC));
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Constructor of the object
     * 
     * @param \PDO $connexion The database connexion
     * 
     * @throws Exception if parameter is not set
     */
    public function __construct(\PDO $connexion) {
        if ($connexion && !empty($connexion)) {
            $this->connexion = $connexion;
        } else {
            throw new Exception("Bad constructor's parameters");
        }
    }

    /**
     * Alias for findOneBy
     * 
     * @param int $id The id of the requested object
     * 
     * @return Object
     * 
     * @throws Exception if id not set
     */
    public function find(int $id) {
        $joinData = null;
        if (!$id || $id == 0) {
            throw new Exception('The parameters given is null or empty');
        }
        try {

            return $this->findOneBy(array($this->primaryKey, $id));
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Request an object by criteria
     * 
     * @param array $criteria list of criteria
     * 
     * @return Object
     * 
     * @throws Exception If criteria not set
     */
    public function findOneBy(array $criteria) {
        $sqlWhere = 'WHERE TRUE ';
        $queryDatas = array();
        if (count($criteria) > 0) {
            foreach ($criteria as $key => $criterium) {
                $sqlWhere .= ' AND ' . $key . '=:' . $key;
                $queryDatas[$key] = $criterium;
            }
            $query = '
                SELECT ' . implode(', ', array_keys($this->fields)) . '
                FROM ' . $this->tableName . ' ' . $this->alias . ' ' . $sqlWhere;

            try {
                return $this->runQuery($query, $queryDatas);
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        } else {
            throw new Exception("Criteria must be specified and cannot be empty");
        }
    }

    /**
     * Request an array of object by criteria
     * 
     * @param array $criteria list of criteria
     * 
     * @return array of object
     * 
     * @throws Exception If array criteria not set
     */
    public function findBy(array $criteria) {
        $sqlWhere = 'WHERE TRUE ';
        $queryDatas = array();
        if (count($criteria) > 0) {
            foreach ($criteria as $key => $criterium) {
                $sqlWhere .= ' AND ' . $key . '=:' . $key;
                $queryDatas[$key] = $criterium;
            }
            $query = '
                SELECT ' . implode(', ', array_keys($this->fields)) . '
                FROM ' . $this->tableName . ' ' . $this->alias . ' ' . $sqlWhere;
            try {

                return $this->runQuery($query, $queryDatas);
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        } else {
            throw new Exception("Criteria must be specified and cannot be empty");
        }
    }

    /**
     * Find all object
     * 
     * @return Object
     * 
     * @throws Exception
     */
    public function findAll() {
        $query = '
            SELECT ' . implode(', ', array_keys($this->fields)) .
                ' FROM ' . $this->tableName . ' ' . $this->alias . ' ';
        try {

            return $this->runQuery($query, null);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Instanciate an entity object with a result set
     * 
     * @param array $rowSet The result of a query fetched
     * 
     * @return Object
     * 
     * @throws Exception
     */
    final protected function instanciate($rowSet) {
        $entityObject = null;
        if (isset($this->objectInstance) && !empty($this->objectInstance)) {
            if (count($rowSet) > 0) {
                try {
                    $entitiesDir = __DIR__ . '/../includes/classes/Entities';
                    require_once $entitiesDir . '/' . $this->objectInstance . 'Entity.php';
                    $entity = new ReflectionClass($this->objectInstance);
                    $properties = $entity->getProperties(ReflectionProperty::IS_PRIVATE);
                    $entityObject = $entity->newInstance();
                    foreach ($properties as $property) {
                        $setAccessor = 'set' . strtoupper(substr($property->name, 0, 1)) . substr($property->name, 1);
                        foreach ($rowSet as $key => $value) {
                            $key = 'set' . $this->camelize($key);
                            if ($key === $setAccessor) {
                                $reflectMethod = new ReflectionMethod($this->objectInstance, $setAccessor);
                                $reflectMethod->invokeArgs($entityObject, array($value));
                            }
                        }
                    }
                    $entityObject = $this->getLinkedObject($entityObject);
                } catch (Exception $e) {
                    throw new Exception($e->getMessage());
                }
            } else {
                throw new Exception("The rowSet parameter must no be empty");
            }
        } else {
            throw new Exception("The object instance must be set");
        }

        return $entityObject;
    }

    /**
     * Get the linked object
     * 
     * @param Object $entityObject The current entity object
     * 
     * @return Object
     * 
     * @throws Exception
     */
    final protected function getLinkedObject($entityObject) {
        $entitiesDir = __DIR__ . '/../Entities';
        $daoDir = __DIR__ . '/../Dao';

        if ($this->objectInstance && $this->links && $entityObject) {
            foreach ($this->links as $link) {
                if (count($link) > 0) {
                    $linkName = $this->camelize($link['table']);
                    $linkName = strtoupper(substr($linkName, 0, 1)) . substr($linkName, 1);

                    require_once $entitiesDir . '/' . $linkName . 'Entity.php';
                    require_once $daoDir . '/' . $linkName . 'Dao.php';

                    $reflectLinkDao = new ReflectionClass($linkName . 'Dao');
                    $linkDaoObject = $reflectLinkDao->newInstance($this->connexion);
                    $entities = $linkDaoObject->findBy(array($link['field'] => $entityObject->getId()));

                    $accessor = strtoupper(substr($link['property'], 0, 1)) . substr($link['property'], 1);

                    $reflectMethod = new ReflectionMethod($entityObject, 'set' . $accessor);
                    $reflectMethod->invokeArgs($entityObject, $entities);

                    return $entityObject;
                }
            }
        } else {
            throw new Exception('One or many parameters are missings, cannot get linked objects');
        }
    }

    final protected function getOneToManyLink($entityObject) {
        
    }

    final protected function getManyToOneLink($entityObject) {
        
    }

    final protected function getManyToManyLink($entityObject) {
        
    }

    /**
     * Transform a string to camelCase
     * 
     * @param string $value The string to camelize
     * 
     * @return string
     * 
     * @throws Exception
     */
    final protected function camelize($value) {
        if ($value) {

            return preg_replace("/([_-\s]?([a-z0-9]+))/e", "ucwords('\\2')", $value);
        } else {
            throw new Exception("Camelize need a value for working.");
        }
    }

}
