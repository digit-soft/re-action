<?php
//TODO: Comeback after DB client implementation

namespace Reaction\Rbac;

use React\Cache\CacheInterface;
use Reaction;
use Reaction\Cache\ExtendedCacheInterface;
use Reaction\Db\DatabaseInterface;
use Reaction\Exceptions\Error;
use Reaction\Exceptions\InvalidArgumentException;
use Reaction\Exceptions\InvalidCallException;
use Reaction\Db\Expressions\Expression;
use Reaction\Db\Query;
use Reaction\DI\Instance;
use Reaction\Promise\ExtendedPromiseInterface;
use function Reaction\Promise\all;
use function Reaction\Promise\allInOrder;
use function Reaction\Promise\resolve;
use function Reaction\Promise\reject;
use function Reaction\Promise\any;

/**
 * DbManager represents an authorization manager that stores authorization information in database.
 *
 * The database connection is specified by [[db]]. The database schema could be initialized by applying migration:
 *
 * ```
 * yii migrate --migrationPath=@yii/rbac/migrations/
 * ```
 *
 * If you don't want to use migration and need SQL instead, files for all databases are in migrations directory.
 *
 * You may change the names of the tables used to store the authorization and rule data by setting [[itemTable]],
 * [[itemChildTable]], [[assignmentTable]] and [[ruleTable]].
 *
 * For more details and usage information on DbManager, see the [guide article on security authorization](guide:security-authorization).
 */
class DbManager extends BaseManager
{
    /**
     * @var DatabaseInterface|array|string the DB connection object or the application component ID of the DB connection.
     * After the DbManager object is created, if you want to change this property, you should only assign it
     * with a DB connection object.
     * Starting from version 2.0.2, this can also be a configuration array for creating the object.
     */
    public $db = 'db';
    /**
     * @var string the name of the table storing authorization items. Defaults to "auth_item".
     */
    public $itemTable = '{{%auth_item}}';
    /**
     * @var string the name of the table storing authorization item hierarchy. Defaults to "auth_item_child".
     */
    public $itemChildTable = '{{%auth_item_child}}';
    /**
     * @var string the name of the table storing authorization item assignments. Defaults to "auth_assignment".
     */
    public $assignmentTable = '{{%auth_assignment}}';
    /**
     * @var string the name of the table storing rules. Defaults to "auth_rule".
     */
    public $ruleTable = '{{%auth_rule}}';
    /**
     * @var ExtendedCacheInterface|array|string the cache used to improve RBAC performance. This can be one of the following:
     *
     * - an application component ID (e.g. `cache`)
     * - a configuration array
     * - a [[\yii\caching\Cache]] object
     *
     * When this is not set, it means caching is not enabled.
     *
     * Note that by enabling RBAC cache, all auth items, rules and auth item parent-child relationships will
     * be cached and loaded into memory. This will improve the performance of RBAC permission check. However,
     * it does require extra memory and as a result may not be appropriate if your RBAC system contains too many
     * auth items. You should seek other RBAC implementations (e.g. RBAC based on Redis storage) in this case.
     *
     * Also note that if you modify RBAC items, rules or parent-child relationships from outside of this component,
     * you have to manually call [[invalidateCache()]] to ensure data consistency.
     */
    public $cache;
    /**
     * @var string the key used to store RBAC data in cache
     * @see cache
     */
    public $cacheKey = 'rbac';

    /**
     * @var Item[] all auth items (name => Item)
     */
    protected $items;
    /**
     * @var Rule[] all auth rules (name => Rule)
     */
    protected $rules;
    /**
     * @var array auth item parent-child relationships (childName => list of parents)
     */
    protected $parents;


    /**
     * Initializes the application component.
     * This method overrides the parent implementation by establishing the database connection.
     */
    public function init()
    {
        parent::init();
        $this->db = Instance::ensure($this->db, DatabaseInterface::class);
        if ($this->cache !== null) {
            $this->cache = Instance::ensure($this->cache, CacheInterface::class);
        }
    }

    private $_checkAccessAssignments = [];

    /**
     * {@inheritdoc}
     */
    public function checkAccess($userId, $permissionName, $params = [])
    {
        //TODO: rewrite static cache of assignments
        if (isset($this->_checkAccessAssignments[(string)$userId])) {
            $basePromise = new Reaction\Promise\Promise(function($r) use ($userId) {
                $r($this->_checkAccessAssignments[(string)$userId]);
            });
        } else {
            $basePromise = $this->getAssignments($userId);
        }

        $assignments = [];
        return $basePromise->then(function($_assignments) use (&$assignments, $userId) {
            if ($this->hasNoAssignments($_assignments)) {
                return reject(new Error("Check access - denied"));
            }
            $assignments = $_assignments;
            $this->_checkAccessAssignments[(string)$userId] = $assignments;
            return $this->loadFromCache();
        })->then(function() use ($userId, $permissionName, $params, &$assignments) {
            if ($this->items !== null) {
                return $this->checkAccessFromCache($userId, $permissionName, $params, $assignments);
            }
            return $this->checkAccessRecursive($userId, $permissionName, $params, $assignments);
        });
    }

    /**
     * Performs access check for the specified user based on the data loaded from cache.
     * This method is internally called by [[checkAccess()]] when [[cache]] is enabled.
     * @param string|int $user the user ID. This should can be either an integer or a string representing
     * the unique identifier of a user. See [[\yii\web\User::id]].
     * @param string $itemName the name of the operation that need access check
     * @param array $params name-value pairs that would be passed to rules associated
     * with the tasks and roles assigned to the user. A param with name 'user' is added to this array,
     * which holds the value of `$userId`.
     * @param Assignment[] $assignments the assignments to the specified user
     * @return ExtendedPromiseInterface with bool whether the operations can be performed by the user.
     */
    protected function checkAccessFromCache($user, $itemName, $params, $assignments)
    {
        if (!isset($this->items[$itemName])) {
            return reject(new Error("Check access from cache - denied"));
        }

        $item = $this->items[$itemName];

        Reaction::debug($item instanceof Role ? "Checking role: $itemName" : "Checking permission: $itemName");

        return $this->executeRule($user, $item, $params)
            ->then(function() use (&$assignments, $itemName, $user, $params) {
                if (isset($assignments[$itemName]) || in_array($itemName, $this->defaultRoles)) {
                    return true;
                }

                if (!empty($this->parents[$itemName])) {
                    $promises = [];
                    foreach ($this->parents[$itemName] as $parent) {
                        $promises[] = $this->checkAccessFromCache($user, $parent, $params, $assignments);
                    }
                    return any($promises)->then(function() { return true; });
                }
                return reject(new Error("Check access from cache - denied"));
            });
    }

    /**
     * Performs access check for the specified user.
     * This method is internally called by [[checkAccess()]].
     * @param string|int $user the user ID. This should can be either an integer or a string representing
     * the unique identifier of a user. See [[\yii\web\User::id]].
     * @param string $itemName the name of the operation that need access check
     * @param array $params name-value pairs that would be passed to rules associated
     * with the tasks and roles assigned to the user. A param with name 'user' is added to this array,
     * which holds the value of `$userId`.
     * @param Assignment[] $assignments the assignments to the specified user
     * @return ExtendedPromiseInterface with bool whether the operations can be performed by the user.
     */
    protected function checkAccessRecursive($user, $itemName, $params, $assignments)
    {
        return $this->getItem($itemName)
            ->then(function($item) use ($user, $params, $itemName) {
                if ($item === null) {
                    return reject(new Error("Check access recursive - denied"));
                }

                Reaction::debug($item instanceof Role ? "Checking role: $itemName" : "Checking permission: $itemName");

                return $this->executeRule($user, $item, $params);
            })
            ->then(function() use (&$assignments, $itemName, $user, $params) {
                if (isset($assignments[$itemName]) || in_array($itemName, $this->defaultRoles)) {
                    return true;
                }
                $query = new Query();
                return $query->select(['parent'])
                    ->from($this->itemChildTable)
                    ->where(['child' => $itemName])
                    ->column($this->db)
                    ->then(function($parents) use ($user, $params, $assignments) {
                        $promises = [];
                        foreach ($parents as $parent) {
                            $promises[] = $this->checkAccessRecursive($user, $parent, $params, $assignments);
                        }
                        return !empty($promises) ? any($promises) : reject(new Error("Check access recursive - denied"));
                    });
            });
    }

    /**
     * {@inheritdoc}
     */
    protected function getItem($name)
    {
        if (empty($name)) {
            return null;
        }

        if (!empty($this->items[$name])) {
            return $this->items[$name];
        }

        return (new Query())->from($this->itemTable)
            ->where(['name' => $name])
            ->one($this->db)
            ->then(
                function($row) {
                    return $this->populateItem($row);
                }
            );
    }

    /**
     * Returns a value indicating whether the database supports cascading update and delete.
     * The default implementation will return false for SQLite database and true for all other databases.
     * @return bool whether the database supports cascading update and delete.
     */
    protected function supportsCascadeUpdate()
    {
        return strncmp($this->db->getDriverName(), 'sqlite', 6) !== 0;
    }

    /**
     * {@inheritdoc}
     */
    protected function addItem($item)
    {
        $time = time();
        if ($item->createdAt === null) {
            $item->createdAt = $time;
        }
        if ($item->updatedAt === null) {
            $item->updatedAt = $time;
        }
        return $this->db->createCommand()
            ->insert($this->itemTable, [
                'name' => $item->name,
                'type' => $item->type,
                'description' => $item->description,
                'rule_name' => $item->ruleName,
                'data' => $item->data === null ? null : serialize($item->data),
                'created_at' => $item->createdAt,
                'updated_at' => $item->updatedAt,
            ])->execute()
            ->then(function() {
                return $this->invalidateCache();
            });
    }

    /**
     * {@inheritdoc}
     */
    protected function removeItem($item)
    {
        $promises = [];
        if (!$this->supportsCascadeUpdate()) {
            $promises[] = $this->db->createCommand()
                ->delete($this->itemChildTable, ['or', '[[parent]]=:name', '[[child]]=:name'], [':name' => $item->name])
                ->execute();
            $promises[] = $this->db->createCommand()
                ->delete($this->assignmentTable, ['item_name' => $item->name])
                ->execute();
        }

        $promises[] = $this->db->createCommand()
            ->delete($this->itemTable, ['name' => $item->name])
            ->execute();

        return allInOrder($promises)->then(function() {
            return $this->invalidateCache();
        });
    }

    /**
     * {@inheritdoc}
     */
    protected function updateItem($name, $item)
    {
        $promises = [];
        if ($item->name !== $name && !$this->supportsCascadeUpdate()) {
            $promises[] = $this->db->createCommand()
                ->update($this->itemChildTable, ['parent' => $item->name], ['parent' => $name])
                ->execute();
            $promises[] = $this->db->createCommand()
                ->update($this->itemChildTable, ['child' => $item->name], ['child' => $name])
                ->execute();
            $promises[] = $this->db->createCommand()
                ->update($this->assignmentTable, ['item_name' => $item->name], ['item_name' => $name])
                ->execute();
        }

        $item->updatedAt = time();

        $promises[] = $this->db->createCommand()
            ->update($this->itemTable, [
                'name' => $item->name,
                'description' => $item->description,
                'rule_name' => $item->ruleName,
                'data' => $item->data === null ? null : serialize($item->data),
                'updated_at' => $item->updatedAt,
            ], [
                'name' => $name,
            ])->execute();

        return allInOrder($promises)->then(function() {
            return $this->invalidateCache();
        });
    }

    /**
     * {@inheritdoc}
     */
    protected function addRule($rule)
    {
        $time = time();
        if ($rule->createdAt === null) {
            $rule->createdAt = $time;
        }
        if ($rule->updatedAt === null) {
            $rule->updatedAt = $time;
        }
        return $this->db->createCommand()
            ->insert($this->ruleTable, [
                'name' => $rule->name,
                'data' => serialize($rule),
                'created_at' => $rule->createdAt,
                'updated_at' => $rule->updatedAt,
            ])->execute()
            ->then(function() {
                return $this->invalidateCache();
            });
    }

    /**
     * {@inheritdoc}
     */
    protected function updateRule($name, $rule)
    {
        $promises = [];
        if ($rule->name !== $name && !$this->supportsCascadeUpdate()) {
            $promises[] = $this->db->createCommand()
                ->update($this->itemTable, ['rule_name' => $rule->name], ['rule_name' => $name])
                ->execute();
        }

        $rule->updatedAt = time();

        $promises[] = $this->db->createCommand()
            ->update($this->ruleTable, [
                'name' => $rule->name,
                'data' => serialize($rule),
                'updated_at' => $rule->updatedAt,
            ], [
                'name' => $name,
            ])->execute();

        return allInOrder($promises)
            ->then(function() {
                return $this->invalidateCache();
            });
    }

    /**
     * {@inheritdoc}
     */
    protected function removeRule($rule)
    {
        $promises = [];
        if (!$this->supportsCascadeUpdate()) {
            $promises[] = $this->db->createCommand()
                ->update($this->itemTable, ['rule_name' => null], ['rule_name' => $rule->name])
                ->execute();
        }

        $promises[] = $this->db->createCommand()
            ->delete($this->ruleTable, ['name' => $rule->name])
            ->execute();

        return allInOrder($promises)
            ->then(function() {
                return $this->invalidateCache();
            });
    }

    /**
     * {@inheritdoc}
     */
    protected function getItems($type)
    {
        $query = (new Query())
            ->from($this->itemTable)
            ->where(['type' => $type]);

        return $query->all($this->db)
            ->then(function($rows) {
                $items = [];
                foreach ($rows as $row) {
                    $items[$row['name']] = $this->populateItem($row);
                }
                return $items;
            });
    }

    /**
     * Populates an auth item with the data fetched from database.
     * @param array $row the data from the auth item table
     * @return Item the populated auth item instance (either Role or Permission)
     */
    protected function populateItem($row)
    {
        $class = $row['type'] == Item::TYPE_PERMISSION ? Permission::class : Role::class;

        if (!isset($row['data']) || ($data = @unserialize(is_resource($row['data']) ? stream_get_contents($row['data']) : $row['data'])) === false) {
            $data = null;
        }

        return new $class([
            'name' => $row['name'],
            'type' => $row['type'],
            'description' => $row['description'],
            'ruleName' => $row['rule_name'],
            'data' => $data,
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at'],
        ]);
    }

    /**
     * {@inheritdoc}
     * The roles returned by this method include the roles assigned via [[$defaultRoles]].
     */
    public function getRolesByUser($userId)
    {
        if ($this->isEmptyUserId($userId)) {
            return resolve([]);
        }

        $query = (new Query())->select('b.*')
            ->from(['a' => $this->assignmentTable, 'b' => $this->itemTable])
            ->where('{{a}}.[[item_name]]={{b}}.[[name]]')
            ->andWhere(['a.user_id' => (string)$userId])
            ->andWhere(['b.type' => Item::TYPE_ROLE]);

        $roles = $this->getDefaultRoleInstances();

        return $query->all($this->db)->then(
            function($rows) use ($roles) {
                foreach ($rows as $row) {
                    $roles[$row['name']] = $this->populateItem($row);
                }
                return $roles;
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getChildRoles($roleName)
    {
        /** @var Role $role */
        $role = null;
        $childrenList = [];
        return $this->getRole($roleName)->then(
            function($roleRow) use (&$role) {
                $role = $roleRow;
                return $this->getChildrenList()
                    ->otherwise(function() { return []; });
            }
        )->then(
            function($childrenRows = []) use (&$childrenList) {
                $childrenList = $childrenRows;
                return $this->getRoles()
                    ->otherwise(function() { return []; });
            }
        )->then(
            function($roles = []) use (&$role, $roleName, &$childrenList) {
                $result = [];
                $this->getChildrenRecursive($roleName, $childrenList, $result);

                $rolesResult = [$roleName => $role];

                $rolesResult += array_filter($roles, function(Role $roleItem) use ($result) {
                    return array_key_exists($roleItem->name, $result);
                });

                return $rolesResult;
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getPermissionsByRole($roleName)
    {
        return $this->getChildrenList()
            ->otherwise(function() { return []; })
            ->then(function($childrenList) use ($roleName) {
                $result = [];
                $this->getChildrenRecursive($roleName, $childrenList, $result);
                if (empty($result)) {
                    return [];
                }
                return (new Query())->from($this->itemTable)->where([
                    'type' => Item::TYPE_PERMISSION,
                    'name' => array_keys($result),
                ])
                    ->all($this->db)
                    ->then(function($rows) {
                        $permissions = [];
                        foreach ($rows as $row) {
                            $permissions[$row['name']] = $this->populateItem($row);
                        }

                        return $permissions;
                    });
            });
    }

    /**
     * {@inheritdoc}
     */
    public function getPermissionsByUser($userId)
    {
        if ($this->isEmptyUserId($userId)) {
            return resolve([]);
        }

        $promises = [
            $this->getDirectPermissionsByUser($userId)->otherwise(function() { return []; }),
            $this->getInheritedPermissionsByUser($userId)->otherwise(function() { return []; }),
        ];
        return all($promises)
            ->then(function($results) {
                return array_merge(...$results);
            });
    }

    /**
     * Returns all permissions that are directly assigned to user.
     * @param string|int $userId the user ID (see [[\yii\web\User::id]])
     * @return ExtendedPromiseInterface with Permission[] all direct permissions that the user has. The array is indexed by the permission names.
     */
    protected function getDirectPermissionsByUser($userId)
    {
        $query = (new Query())->select('b.*')
            ->from(['a' => $this->assignmentTable, 'b' => $this->itemTable])
            ->where('{{a}}.[[item_name]]={{b}}.[[name]]')
            ->andWhere(['a.user_id' => (string)$userId])
            ->andWhere(['b.type' => Item::TYPE_PERMISSION]);

        return $query->all($this->db)->then(
            function($rows) {
                $permissions = [];
                foreach ($rows as $row) {
                    $permissions[$row['name']] = $this->populateItem($row);
                }

                return $permissions;
            }
        );
    }

    /**
     * Returns all permissions that the user inherits from the roles assigned to him.
     * @param string|int $userId the user ID (see [[\yii\web\User::id]])
     * @return ExtendedPromiseInterface with Permission[] all inherited permissions that the user has. The array is indexed by the permission names.
     */
    protected function getInheritedPermissionsByUser($userId)
    {
        $childrenList = [];
        return $this->getChildrenList()->then(
            function($_childrenList) use (&$childrenList, $userId) {
                $childrenList = $_childrenList;
                return (new Query())->select('item_name')
                    ->from($this->assignmentTable)
                    ->where(['user_id' => (string)$userId])
                    ->column($this->db);
            }
        )->then(
            function($roleNames) use (&$childrenList) {
                $result = [];
                foreach ($roleNames as $roleName) {
                    $this->getChildrenRecursive($roleName, $childrenList, $result);
                }

                if (empty($result)) {
                    return [];
                }

                return (new Query())->from($this->itemTable)->where([
                    'type' => Item::TYPE_PERMISSION,
                    'name' => array_keys($result),
                ])->all($this->db)
                    ->otherwise(function() { return []; })
                    ->then(
                        function($items) {
                            $permissions = [];
                            foreach ($items as $row) {
                                $permissions[$row['name']] = $this->populateItem($row);
                            }

                            return $permissions;
                        }
                    );
            }
        );
    }

    /**
     * Returns the children for every parent.
     * @return ExtendedPromiseInterface with array the children list. Each array key is a parent item name,
     * and the corresponding array value is a list of child item names.
     */
    protected function getChildrenList()
    {
        return (new Query())->from($this->itemChildTable)
            ->all($this->db)
            ->then(
                function($rows) {
                    $parents = [];
                    foreach ($rows as $row) {
                        $parents[$row['parent']][] = $row['child'];
                    }

                    return $parents;
                }
            );
    }

    /**
     * Recursively finds all children and grand children of the specified item.
     * @param string $name the name of the item whose children are to be looked for.
     * @param array $childrenList the child list built via [[getChildrenList()]]
     * @param array $result the children and grand children (in array keys)
     */
    protected function getChildrenRecursive($name, $childrenList, &$result)
    {
        if (isset($childrenList[$name])) {
            foreach ($childrenList[$name] as $child) {
                $result[$child] = true;
                $this->getChildrenRecursive($child, $childrenList, $result);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getRule($name)
    {
        if ($this->rules !== null) {
            return isset($this->rules[$name])
                ? resolve($this->rules[$name])
                : reject(new Error("Rule '$name' not found"));
        }

        return (new Query())->select(['data'])
            ->from($this->ruleTable)
            ->where(['name' => $name])
            ->one($this->db)
            ->then(
                function($row) use ($name) {
                    if (empty($row)) {
                        return reject(new Error("Rule '$name' not found"));
                    }
                    $data = $row['data'];
                    if (is_resource($data)) {
                        $data = stream_get_contents($data);
                    }

                    return unserialize($data);
                }
            );
    }

    /**
     * {@inheritdoc}
     */
    public function getRules()
    {
        if ($this->rules !== null) {
            return resolve($this->rules);
        }

        return (new Query())->from($this->ruleTable)
            ->all($this->db)
            ->then(
                function($rows) {
                    $rules = [];
                    foreach ($rows as $row) {
                        $data = $row['data'];
                        if (is_resource($data)) {
                            $data = stream_get_contents($data);
                        }
                        $rules[$row['name']] = unserialize($data);
                    }

                    return $rules;
                }
            );
    }

    /**
     * {@inheritdoc}
     */
    public function getAssignment($roleName, $userId)
    {
        if ($this->isEmptyUserId($userId)) {
            return reject(new Error("User id is empty"));
        }

        return (new Query())->from($this->assignmentTable)
            ->where(['user_id' => (string)$userId, 'item_name' => $roleName])
            ->one($this->db)
            ->then(
                function($row) {
                    return new Assignment([
                        'userId' => $row['user_id'],
                        'roleName' => $row['item_name'],
                        'createdAt' => $row['created_at'],
                    ]);
                },
                function() use ($userId, $roleName) {
                    throw new Error("No assignment found for user '$userId' and role '$roleName'");
                }
            );
    }

    /**
     * {@inheritdoc}
     */
    public function getAssignments($userId)
    {
        if ($this->isEmptyUserId($userId)) {
            return resolve([]);
        }

        return (new Query())
            ->from($this->assignmentTable)
            ->where(['user_id' => (string)$userId])
            ->all($this->db)
            ->then(
                function($rows) {
                    $assignments = [];
                    foreach ($rows as $row) {
                        $assignments[$row['item_name']] = new Assignment([
                            'userId' => $row['user_id'],
                            'roleName' => $row['item_name'],
                            'createdAt' => $row['created_at'],
                        ]);
                    }

                    return $assignments;
                }
            );
    }

    /**
     * {@inheritdoc}
     */
    public function canAddChild($parent, $child)
    {
        return $this->detectLoop($parent, $child);
    }

    /**
     * {@inheritdoc}
     */
    public function addChild($parent, $child)
    {
        if ($parent->name === $child->name) {
            throw new InvalidArgumentException("Cannot add '{$parent->name}' as a child of itself.");
        }

        if ($parent instanceof Permission && $child instanceof Role) {
            throw new InvalidArgumentException('Cannot add a role as a child of a permission.');
        }

        return $this->detectLoop($parent, $child)
            ->then(function() use ($child, $parent) {
                return $this->db->createCommand()
                    ->insert($this->itemChildTable, ['parent' => $parent->name, 'child' => $child->name])
                    ->execute()
                    ->then(function() {
                        return $this->invalidateCache();
                    });
            }, function() use ($child, $parent) {
                throw new InvalidCallException("Cannot add '{$child->name}' as a child of '{$parent->name}'. A loop has been detected.");
            });
    }

    /**
     * {@inheritdoc}
     */
    public function removeChild($parent, $child)
    {
        return $this->db->createCommand()
            ->delete($this->itemChildTable, ['parent' => $parent->name, 'child' => $child->name])
            ->execute()
            ->then(function($count = 0) {
                return $this->invalidateCache()
                    ->then(function() use ($count) {
                        return $count > 0;
                    });
            });
    }

    /**
     * {@inheritdoc}
     */
    public function removeChildren($parent)
    {
        return $this->db->createCommand()
            ->delete($this->itemChildTable, ['parent' => $parent->name])
            ->execute()
            ->then(function($count = 0) {
                return $this->invalidateCache()
                    ->then(function() use ($count) {
                        return $count > 0;
                    });
            });
    }

    /**
     * {@inheritdoc}
     */
    public function hasChild($parent, $child)
    {
        return (new Query())
            ->from($this->itemChildTable)
            ->where(['parent' => $parent->name, 'child' => $child->name])
            ->one($this->db)
            ->then(function() {
                return true;
            });
    }

    /**
     * {@inheritdoc}
     */
    public function getChildren($name)
    {
        return (new Query())
            ->select(['name', 'type', 'description', 'rule_name', 'data', 'created_at', 'updated_at'])
            ->from([$this->itemTable, $this->itemChildTable])
            ->where(['parent' => $name, 'name' => new Expression('[[child]]')])
            ->all($this->db)
            ->then(function($rows) {
                $children = [];
                foreach ($rows as $row) {
                    $children[$row['name']] = $this->populateItem($row);
                }
                return $children;
            });
    }

    /**
     * Checks whether there is a loop in the authorization item hierarchy.
     * @param Item $parent the parent item
     * @param Item $child the child item to be added to the hierarchy
     * @return ExtendedPromiseInterface with bool whether a loop exists
     */
    protected function detectLoop($parent, $child)
    {
        if ($child->name === $parent->name) {
            return reject(true);
        }

        return $this->getChildren($child->name)
            ->then(function($children) use ($parent) {
                $promises = [];
                foreach ($children as $grandchild) {
                    $promises[] = $this->detectLoop($parent, $grandchild);
                }
                return !empty($promises)
                    ? all($promises)->then(function() { return false; })
                    : false;
            }, function() {
                return false;
            });
    }

    /**
     * {@inheritdoc}
     */
    public function assign($role, $userId)
    {
        $assignment = new Assignment([
            'userId' => $userId,
            'roleName' => $role->name,
            'createdAt' => time(),
        ]);

        return $this->db->createCommand()
            ->insert($this->assignmentTable, [
                'user_id' => $assignment->userId,
                'item_name' => $assignment->roleName,
                'created_at' => $assignment->createdAt,
            ])
            ->execute()
            ->then(
                function() use ($assignment, $userId) {
                    unset($this->_checkAccessAssignments[(string)$userId]);
                    return $assignment;
                }
            );
    }

    /**
     * {@inheritdoc}
     */
    public function revoke($role, $userId)
    {
        if ($this->isEmptyUserId($userId)) {
            return reject(new Error("Empty user ID"));
        }

        unset($this->_checkAccessAssignments[(string)$userId]);
        return $this->db->createCommand()
            ->delete($this->assignmentTable, ['user_id' => (string)$userId, 'item_name' => $role->name])
            ->execute()
            ->then(function($count = 0) {
                return $count > 0;
            });
    }

    /**
     * {@inheritdoc}
     */
    public function revokeAll($userId)
    {
        if ($this->isEmptyUserId($userId)) {
            return reject(new Error("Empty user ID"));
        }

        unset($this->_checkAccessAssignments[(string)$userId]);
        return $this->db->createCommand()
            ->delete($this->assignmentTable, ['user_id' => (string)$userId])
            ->execute()
            ->then(function($count = 0) {
                return $count > 0;
            });
    }

    /**
     * {@inheritdoc}
     */
    public function removeAll()
    {
        $promises = [
            $this->removeAllAssignments(),
            $this->db->createCommand()->delete($this->itemChildTable)->execute(),
            $this->db->createCommand()->delete($this->itemTable)->execute(),
            $this->db->createCommand()->delete($this->ruleTable)->execute(),
        ];
        return all($promises)
            ->then(function() {
                return $this->invalidateCache();
            });
    }

    /**
     * {@inheritdoc}
     */
    public function removeAllPermissions()
    {
        $this->removeAllItems(Item::TYPE_PERMISSION);
    }

    /**
     * {@inheritdoc}
     */
    public function removeAllRoles()
    {
        $this->removeAllItems(Item::TYPE_ROLE);
    }

    /**
     * Removes all auth items of the specified type.
     * @param int $type the auth item type (either Item::TYPE_PERMISSION or Item::TYPE_ROLE)
     * @return ExtendedPromiseInterface when finished
     */
    protected function removeAllItems($type)
    {
        if (!$this->supportsCascadeUpdate()) {
            $namesPromise = (new Query())
                ->select(['name'])
                ->from($this->itemTable)
                ->where(['type' => $type])
                ->column($this->db);
        } else {
            $namesPromise = new Reaction\Promise\Promise(function($r) {
                $r([]);
            });
        }

        return $namesPromise
            ->then(function($names) use ($type) {
                $promises = [];
                if (!$this->supportsCascadeUpdate()) {
                    if (empty($names)) {
                        return resolve(true);
                    }
                    $key = $type == Item::TYPE_PERMISSION ? 'child' : 'parent';
                    $promises[] = $this->db->createCommand()
                        ->delete($this->itemChildTable, [$key => $names])
                        ->execute();
                    $promises[] = $this->db->createCommand()
                        ->delete($this->assignmentTable, ['item_name' => $names])
                        ->execute();
                }
                $promises[] = $this->db->createCommand()
                    ->delete($this->itemTable, ['type' => $type])
                    ->execute();
                return allInOrder($promises);
            })->then(function() {
                return $this->invalidateCache();
            });
    }

    /**
     * {@inheritdoc}
     */
    public function removeAllRules()
    {
        $promises = [];
        if (!$this->supportsCascadeUpdate()) {
            $promises[] = $this->db->createCommand()
                ->update($this->itemTable, ['rule_name' => null])
                ->execute();
        }

        $promises[] = $this->db->createCommand()->delete($this->ruleTable)->execute();

        return allInOrder($promises)
            ->then(function() {
                return $this->invalidateCache();
            });
    }

    /**
     * {@inheritdoc}
     */
    public function removeAllAssignments()
    {
        $this->_checkAccessAssignments = [];
        return $this->db->createCommand()->delete($this->assignmentTable)->execute();
    }

    /**
     * Invalidate all cache
     * @return ExtendedPromiseInterface
     */
    public function invalidateCache()
    {
        $this->_checkAccessAssignments = [];
        if ($this->cache !== null) {
            $this->items = null;
            $this->rules = null;
            $this->parents = null;
            return $this->cache->remove($this->cacheKey)
                ->then(function() {
                    return true;
                }, function() {
                    return true;
                });
        }
        return resolve(true);
    }

    /**
     * Load/save cache
     * @return ExtendedPromiseInterface
     */
    public function loadFromCache()
    {
        if ($this->items !== null || !$this->cache instanceof ExtendedCacheInterface) {
            return resolve(true);
        }

        return $this->cache->get($this->cacheKey)
            ->otherwise(function() { return []; })
            ->then(function($data) {
                if (is_array($data) && isset($data[0], $data[1], $data[2])) {
                    list($this->items, $this->rules, $this->parents) = $data;
                    return true;
                }
                $promises = [
                    //Items
                    (new Query())->from($this->itemTable)->all($this->db),
                    //Rules
                    (new Query())->from($this->ruleTable)->all($this->db),
                    //Children
                    (new Query())->from($this->itemChildTable)->all($this->db),
                ];

                return all($promises)
                    ->then(function($results = []) {
                        $items = isset($results[0]) ? $results[0] : [];
                        $rules = isset($results[1]) ? $results[1] : [];
                        $itemsChildren = isset($results[2]) ? $results[2] : [];

                        $this->items = [];
                        foreach ($items as $row) {
                            $this->items[$row['name']] = $this->populateItem($row);
                        }

                        $this->rules = [];
                        foreach ($rules as $row) {
                            $data = $row['data'];
                            if (is_resource($data)) {
                                $data = stream_get_contents($data);
                            }
                            $this->rules[$row['name']] = unserialize($data);
                        }

                        $this->parents = [];
                        foreach ($itemsChildren as $row) {
                            if (isset($this->items[$row['child']])) {
                                $this->parents[$row['child']][] = $row['parent'];
                            }
                        }

                        return $this->cache->set($this->cacheKey, [$this->items, $this->rules, $this->parents]);
                    });
            });
    }

    /**
     * Returns all role assignment information for the specified role.
     * @param string $roleName
     * @return ExtendedPromiseInterface with string[] the ids. An empty array will be
     * returned if role is not assigned to any user.
     */
    public function getUserIdsByRole($roleName)
    {
        if (empty($roleName)) {
            return resolve([]);
        }

        return (new Query())->select('[[user_id]]')
            ->from($this->assignmentTable)
            ->where(['item_name' => $roleName])
            ->column($this->db);
    }

    /**
     * Check whether $userId is empty.
     * @param mixed $userId
     * @return bool
     */
    private function isEmptyUserId($userId)
    {
        return !isset($userId) || $userId === '';
    }
}
