<?php

namespace Reaction\Rbac;

use Reaction\Promise\ExtendedPromiseInterface;
use Reaction;
use Reaction\Exceptions\Error;
use Reaction\Exceptions\InvalidArgumentException;
use Reaction\Exceptions\InvalidCallException;
use Reaction\Helpers\VarDumper;
use function Reaction\Promise\resolve;
use function Reaction\Promise\reject;
use function Reaction\Promise\all;
use function Reaction\Promise\any;

/**
 * PhpManager represents an authorization manager that stores authorization
 * information in terms of a PHP script file.
 *
 * The authorization data will be saved to and loaded from three files
 * specified by [[itemFile]], [[assignmentFile]] and [[ruleFile]].
 *
 * PhpManager is mainly suitable for authorization data that is not too big
 * (for example, the authorization data for a personal blog system).
 * Use [[DbManager]] for more complex authorization data.
 *
 * Note that PhpManager is not compatible with facebooks [HHVM](http://hhvm.com/) because
 * it relies on writing php files and including them afterwards which is not supported by HHVM.
 *
 * For more details and usage information on PhpManager, see the [guide article on security authorization](guide:security-authorization).
 */
class PhpManager extends BaseManager
{
    /**
     * @var string the path of the PHP script that contains the authorization items.
     * This can be either a file path or a [path alias](guide:concept-aliases) to the file.
     * Make sure this file is writable by the Web server process if the authorization needs to be changed online.
     * @see loadFromFile()
     * @see saveToFile()
     */
    public $itemFile = '@app/Rbac/items.php';
    /**
     * @var string the path of the PHP script that contains the authorization assignments.
     * This can be either a file path or a [path alias](guide:concept-aliases) to the file.
     * Make sure this file is writable by the Web server process if the authorization needs to be changed online.
     * @see loadFromFile()
     * @see saveToFile()
     */
    public $assignmentFile = '@app/Rbac/assignments.php';
    /**
     * @var string the path of the PHP script that contains the authorization rules.
     * This can be either a file path or a [path alias](guide:concept-aliases) to the file.
     * Make sure this file is writable by the Web server process if the authorization needs to be changed online.
     * @see loadFromFile()
     * @see saveToFile()
     */
    public $ruleFile = '@app/Rbac/rules.php';

    /**
     * @var Item[]
     */
    protected $items = []; // itemName => item
    /**
     * @var array
     */
    protected $children = []; // itemName, childName => child
    /**
     * @var array
     */
    protected $assignments = []; // userId, itemName => assignment
    /**
     * @var Rule[]
     */
    protected $rules = []; // ruleName => rule


    /**
     * Initializes the application component.
     * This method overrides parent implementation by loading the authorization data
     * from PHP script.
     */
    public function init()
    {
        parent::init();
        $this->itemFile = Reaction::$app->getAlias($this->itemFile);
        $this->assignmentFile = Reaction::$app->getAlias($this->assignmentFile);
        $this->ruleFile = Reaction::$app->getAlias($this->ruleFile);
        $this->load();
    }

    /**
     * {@inheritdoc}
     */
    public function checkAccess($userId, $permissionName, $params = [])
    {
        return $this->getAssignments($userId)
            ->then(function($assignments) use ($userId, $permissionName, $params) {
                if ($this->hasNoAssignments($assignments)) {
                    return reject(new Error("No assignments for '$userId'"));
                }

                return $this->checkAccessRecursive($userId, $permissionName, $params, $assignments);
            });
    }

    /**
     * {@inheritdoc}
     */
    public function getAssignments($userId)
    {
        return isset($this->assignments[$userId])
            ? resolve($this->assignments[$userId])
            : reject(new Error("No assignments for '$userId'"));
    }

    /**
     * Performs access check for the specified user.
     * This method is internally called by [[checkAccess()]].
     *
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
        if (!isset($this->items[$itemName])) {
            return reject(new Error("Check access recursive - denied"));
        }

        /* @var $item Item */
        $item = $this->items[$itemName];
        Reaction::debug($item instanceof Role ? "Checking role: $itemName" : "Checking permission : $itemName");

        return $this->executeRule($user, $item, $params)
            ->then(function() use ($assignments, $itemName, $user, $params) {
                if (isset($assignments[$itemName]) || in_array($itemName, $this->defaultRoles)) {
                    return true;
                }

                $promises = [];
                foreach ($this->children as $parentName => $children) {
                    if (isset($children[$itemName])) {
                        $promises[] = $this->checkAccessRecursive($user, $parentName, $params, $assignments);
                    }
                }
                return !empty($promises) ? any($promises) : reject(new Error("Check access recursive - denied"));
            });
    }

    /**
     * {@inheritdoc}
     */
    public function canAddChild($parent, $child)
    {
        return !$this->detectLoop($parent, $child) ? resolve(true) : reject(false);
    }

    /**
     * {@inheritdoc}
     */
    public function addChild($parent, $child)
    {
        if (!isset($this->items[$parent->name], $this->items[$child->name])) {
            throw new InvalidArgumentException("Either '{$parent->name}' or '{$child->name}' does not exist.");
        }

        if ($parent->name === $child->name) {
            throw new InvalidArgumentException("Cannot add '{$parent->name} ' as a child of itself.");
        }
        if ($parent instanceof Permission && $child instanceof Role) {
            throw new InvalidArgumentException('Cannot add a role as a child of a permission.');
        }

        if ($this->detectLoop($parent, $child)) {
            throw new InvalidCallException("Cannot add '{$child->name}' as a child of '{$parent->name}'. A loop has been detected.");
        }
        if (isset($this->children[$parent->name][$child->name])) {
            throw new InvalidCallException("The item '{$parent->name}' already has a child '{$child->name}'.");
        }
        $this->children[$parent->name][$child->name] = $this->items[$child->name];
        return $this->saveItems();
    }

    /**
     * Checks whether there is a loop in the authorization item hierarchy.
     *
     * @param Item $parent parent item
     * @param Item $child the child item that is to be added to the hierarchy
     * @return bool whether a loop exists
     */
    protected function detectLoop($parent, $child)
    {
        if ($child->name === $parent->name) {
            return true;
        }
        if (!isset($this->children[$child->name], $this->items[$parent->name])) {
            return false;
        }
        foreach ($this->children[$child->name] as $grandchild) {
            /* @var $grandchild Item */
            if ($this->detectLoop($parent, $grandchild)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function removeChild($parent, $child)
    {
        if (isset($this->children[$parent->name][$child->name])) {
            unset($this->children[$parent->name][$child->name]);
            return $this->saveItems();
        }

        return reject(new Error("'{$parent->name}' has no child '{$child->name}'"));
    }

    /**
     * {@inheritdoc}
     */
    public function removeChildren($parent)
    {
        if (isset($this->children[$parent->name])) {
            unset($this->children[$parent->name]);
            return $this->saveItems();
        }

        return reject(new Error("'{$parent->name}' has no children"));
    }

    /**
     * {@inheritdoc}
     */
    public function hasChild($parent, $child)
    {
        return isset($this->children[$parent->name][$child->name])
            ? resolve(true)
            : reject(false);
    }

    /**
     * {@inheritdoc}
     */
    public function assign($role, $userId)
    {
        if (!isset($this->items[$role->name])) {
            throw new InvalidArgumentException("Unknown role '{$role->name}'.");
        } elseif (isset($this->assignments[$userId][$role->name])) {
            throw new InvalidArgumentException("Authorization item '{$role->name}' has already been assigned to user '$userId'.");
        }

        $this->assignments[$userId][$role->name] = new Assignment([
            'userId' => $userId,
            'roleName' => $role->name,
            'createdAt' => time(),
        ]);
        return $this->saveAssignments()
            ->then(function() use ($userId, $role) {
                return $this->assignments[$userId][$role->name];
            });
    }

    /**
     * {@inheritdoc}
     */
    public function revoke($role, $userId)
    {
        if (isset($this->assignments[$userId][$role->name])) {
            unset($this->assignments[$userId][$role->name]);
            return $this->saveAssignments();
        }

        return reject(new Error("User '$userId' has no role '$role->name'"));
    }

    /**
     * {@inheritdoc}
     */
    public function revokeAll($userId)
    {
        if (isset($this->assignments[$userId]) && is_array($this->assignments[$userId])) {
            foreach ($this->assignments[$userId] as $itemName => $value) {
                unset($this->assignments[$userId][$itemName]);
            }
            return $this->saveAssignments();
        }

        return reject(new Error("No assignments for user '$userId'"));
    }

    /**
     * {@inheritdoc}
     */
    public function getAssignment($roleName, $userId)
    {
        return isset($this->assignments[$userId][$roleName])
            ? resolve($this->assignments[$userId][$roleName])
            : reject(new Error("Assignment for user '$userId' with role '$roleName' not found"));
    }

    /**
     * {@inheritdoc}
     */
    public function getItems($type)
    {
        $items = [];

        foreach ($this->items as $name => $item) {
            /* @var $item Item */
            if ($item->type == $type) {
                $items[$name] = $item;
            }
        }

        return resolve($items);
    }


    /**
     * {@inheritdoc}
     */
    public function removeItem($item)
    {
        if (isset($this->items[$item->name])) {
            foreach ($this->children as &$children) {
                unset($children[$item->name]);
            }
            foreach ($this->assignments as &$assignments) {
                unset($assignments[$item->name]);
            }
            unset($this->items[$item->name]);
            $promises = [
                $this->saveItems(),
                $this->saveAssignments(),
            ];
            return all($promises);
        }

        return reject(new Error("Item '{$item->name}' not found"));
    }

    /**
     * {@inheritdoc}
     */
    public function getItem($name)
    {
        return isset($this->items[$name]) ? resolve($this->items[$name]) : reject(new Error("Item '$name' not found"));
    }

    /**
     * {@inheritdoc}
     */
    public function updateRule($name, $rule)
    {
        if ($rule->name !== $name) {
            unset($this->rules[$name]);
        }
        $this->rules[$rule->name] = $rule;
        return $this->saveRules();
    }

    /**
     * {@inheritdoc}
     */
    public function getRule($name)
    {
        return isset($this->rules[$name]) ? resolve($this->rules[$name]) : reject(new Error("Rule '$name' not found"));
    }

    /**
     * {@inheritdoc}
     */
    public function getRules()
    {
        return resolve($this->rules);
    }

    /**
     * {@inheritdoc}
     * The roles returned by this method include the roles assigned via [[$defaultRoles]].
     */
    public function getRolesByUser($userId)
    {
        $roles = $this->getDefaultRoleInstances();
        return $this->getAssignments($userId)
            ->otherwise(function() {
                return [];
            })
            ->then(function($assignments) use ($roles) {
                foreach ($assignments as $name => $assignment) {
                    $role = $this->items[$assignment->roleName];
                    if ($role->type === Item::TYPE_ROLE) {
                        $roles[$name] = $role;
                    }
                }

                return $roles;
            });
    }

    /**
     * {@inheritdoc}
     */
    public function getChildRoles($roleName)
    {
        return $this->getRole($roleName)
            ->then(function($role) use ($roleName) {
                if ($role === null) {
                    throw new InvalidArgumentException("Role \"$roleName\" not found.");
                }

                $result = [];
                $this->getChildrenRecursive($roleName, $result);

                $roles = [$roleName => $role];

                return $this->getRoles()
                    ->otherwise(function() {
                        return [];
                    })
                    ->then(function($_roles) use ($roles, &$result) {
                        $roles += array_filter($_roles, function(Role $roleItem) use ($result) {
                            return array_key_exists($roleItem->name, $result);
                        });
                        return $roles;
                    });
            });
    }

    /**
     * {@inheritdoc}
     */
    public function getPermissionsByRole($roleName)
    {
        $result = [];
        $this->getChildrenRecursive($roleName, $result);
        if (empty($result)) {
            return resolve([]);
        }
        $permissions = [];
        foreach (array_keys($result) as $itemName) {
            if (isset($this->items[$itemName]) && $this->items[$itemName] instanceof Permission) {
                $permissions[$itemName] = $this->items[$itemName];
            }
        }

        return resolve($permissions);
    }

    /**
     * Recursively finds all children and grand children of the specified item.
     *
     * @param string $name the name of the item whose children are to be looked for.
     * @param array $result the children and grand children (in array keys)
     */
    protected function getChildrenRecursive($name, &$result)
    {
        if (isset($this->children[$name])) {
            foreach ($this->children[$name] as $child) {
                $result[$child->name] = true;
                $this->getChildrenRecursive($child->name, $result);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getPermissionsByUser($userId)
    {
        $promises = [
            $this->getDirectPermissionsByUser($userId)->otherwise(function() { return []; }),
            $this->getInheritedPermissionsByUser($userId)->otherwise(function() { return []; }),
        ];

        return all($promises)
            ->then(function($results = []) {
                return array_merge(...$results);
            });
    }

    /**
     * Returns all permissions that are directly assigned to user.
     * @param string|int $userId the user ID (see [[\Reaction\Web\User::id]])
     * @return ExtendedPromiseInterface with Permission[] all direct permissions that the user has. The array is indexed by the permission names.
     */
    protected function getDirectPermissionsByUser($userId)
    {
        return $this->getAssignments($userId)
            ->then(function($assignments) {
                $permissions = [];
                foreach ($assignments as $name => $assignment) {
                    $permission = $this->items[$assignment->roleName];
                    if ($permission->type === Item::TYPE_PERMISSION) {
                        $permissions[$name] = $permission;
                    }
                }

                return $permissions;
            });
    }

    /**
     * Returns all permissions that the user inherits from the roles assigned to him.
     * @param string|int $userId the user ID (see [[\yii\web\User::id]])
     * @return ExtendedPromiseInterface with Permission[] all inherited permissions that the user has. The array is indexed by the permission names.
     */
    protected function getInheritedPermissionsByUser($userId)
    {
        return $this->getAssignments($userId)
            ->then(function($assignments = []) {
                $result = [];
                foreach (array_keys($assignments) as $roleName) {
                    $this->getChildrenRecursive($roleName, $result);
                }

                if (empty($result)) {
                    return [];
                }

                $permissions = [];
                foreach (array_keys($result) as $itemName) {
                    if (isset($this->items[$itemName]) && $this->items[$itemName] instanceof Permission) {
                        $permissions[$itemName] = $this->items[$itemName];
                    }
                }

                return $permissions;
            });
    }

    /**
     * {@inheritdoc}
     */
    public function getChildren($name)
    {
        return isset($this->children[$name]) ? resolve($this->children[$name]) : resolve([]);
    }

    /**
     * {@inheritdoc}
     */
    public function removeAll()
    {
        $this->children = [];
        $this->items = [];
        $this->assignments = [];
        $this->rules = [];
        return $this->save();
    }

    /**
     * {@inheritdoc}
     */
    public function removeAllPermissions()
    {
        return $this->removeAllItems(Item::TYPE_PERMISSION);
    }

    /**
     * {@inheritdoc}
     */
    public function removeAllRoles()
    {
        return $this->removeAllItems(Item::TYPE_ROLE);
    }

    /**
     * Removes all auth items of the specified type.
     * @param int $type the auth item type (either Item::TYPE_PERMISSION or Item::TYPE_ROLE)
     * @return ExtendedPromiseInterface
     */
    protected function removeAllItems($type)
    {
        $names = [];
        foreach ($this->items as $name => $item) {
            if ($item->type == $type) {
                unset($this->items[$name]);
                $names[$name] = true;
            }
        }
        if (empty($names)) {
            return resolve(true);
        }

        foreach ($this->assignments as $i => $assignments) {
            foreach ($assignments as $n => $assignment) {
                if (isset($names[$assignment->roleName])) {
                    unset($this->assignments[$i][$n]);
                }
            }
        }
        foreach ($this->children as $name => $children) {
            if (isset($names[$name])) {
                unset($this->children[$name]);
            } else {
                foreach ($children as $childName => $item) {
                    if (isset($names[$childName])) {
                        unset($children[$childName]);
                    }
                }
                $this->children[$name] = $children;
            }
        }

        return $this->saveItems();
    }

    /**
     * {@inheritdoc}
     */
    public function removeAllRules()
    {
        foreach ($this->items as $item) {
            $item->ruleName = null;
        }
        $this->rules = [];
        return $this->saveRules();
    }

    /**
     * {@inheritdoc}
     */
    public function removeAllAssignments()
    {
        $this->assignments = [];
        return $this->saveAssignments();
    }

    /**
     * {@inheritdoc}
     */
    protected function removeRule($rule)
    {
        if (isset($this->rules[$rule->name])) {
            unset($this->rules[$rule->name]);
            foreach ($this->items as $item) {
                if ($item->ruleName === $rule->name) {
                    $item->ruleName = null;
                }
            }
            return $this->saveRules();
        }

        return reject(new Error("Rule '{$rule->name}' not found"));
    }

    /**
     * {@inheritdoc}
     */
    protected function addRule($rule)
    {
        $this->rules[$rule->name] = $rule;
        return $this->saveRules();
    }

    /**
     * {@inheritdoc}
     */
    protected function updateItem($name, $item)
    {
        if ($name !== $item->name) {
            if (isset($this->items[$item->name])) {
                reject(new InvalidArgumentException("Unable to change the item name. The name '{$item->name}' is already used by another item."));
            }

            // Remove old item in case of renaming
            unset($this->items[$name]);

            if (isset($this->children[$name])) {
                $this->children[$item->name] = $this->children[$name];
                unset($this->children[$name]);
            }
            foreach ($this->children as &$children) {
                if (isset($children[$name])) {
                    $children[$item->name] = $children[$name];
                    unset($children[$name]);
                }
            }
            foreach ($this->assignments as &$assignments) {
                if (isset($assignments[$name])) {
                    $assignments[$item->name] = $assignments[$name];
                    $assignments[$item->name]->roleName = $item->name;
                    unset($assignments[$name]);
                }
            }
            $basePromise = $this->saveAssignments();
        } else {
            $basePromise = resolve(true);
        }

        $this->items[$item->name] = $item;

        return $basePromise->then(function() {
            return $this->saveItems();
        });
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

        $this->items[$item->name] = $item;

        return $this->saveItems();
    }

    /**
     * Loads authorization data from persistent storage.
     */
    protected function load()
    {
        $this->children = [];
        $this->rules = [];
        $this->assignments = [];
        $this->items = [];

        $items = $this->loadFromFile($this->itemFile);
        $itemsMtime = @filemtime($this->itemFile);
        $assignments = $this->loadFromFile($this->assignmentFile);
        $assignmentsMtime = @filemtime($this->assignmentFile);
        $rules = $this->loadFromFile($this->ruleFile);

        foreach ($items as $name => $item) {
            $class = $item['type'] == Item::TYPE_PERMISSION ? Permission::class : Role::class;

            $this->items[$name] = new $class([
                'name' => $name,
                'description' => isset($item['description']) ? $item['description'] : null,
                'ruleName' => isset($item['ruleName']) ? $item['ruleName'] : null,
                'data' => isset($item['data']) ? $item['data'] : null,
                'createdAt' => $itemsMtime,
                'updatedAt' => $itemsMtime,
            ]);
        }

        foreach ($items as $name => $item) {
            if (isset($item['children'])) {
                foreach ($item['children'] as $childName) {
                    if (isset($this->items[$childName])) {
                        $this->children[$name][$childName] = $this->items[$childName];
                    }
                }
            }
        }

        foreach ($assignments as $userId => $roles) {
            foreach ($roles as $role) {
                $this->assignments[$userId][$role] = new Assignment([
                    'userId' => $userId,
                    'roleName' => $role,
                    'createdAt' => $assignmentsMtime,
                ]);
            }
        }

        foreach ($rules as $name => $ruleData) {
            $this->rules[$name] = unserialize($ruleData);
        }
    }

    /**
     * Saves authorization data into persistent storage.
     * @return ExtendedPromiseInterface when finished
     */
    protected function save()
    {
        $promises = [
            $this->saveItems(),
            $this->saveAssignments(),
            $this->saveRules(),
        ];
        return all($promises);
    }

    /**
     * Loads the authorization data from a PHP script file.
     *
     * @param string $file the file path.
     * @return array the authorization data
     * @see saveToFile()
     */
    protected function loadFromFile($file)
    {
        if (is_file($file)) {
            return require $file;
        }

        return [];
    }

    /**
     * Saves the authorization data to a PHP script file.
     *
     * @param array  $data the authorization data
     * @param string $filePath the file path.
     * @see loadFromFile()
     * @return ExtendedPromiseInterface
     */
    protected function saveToFile($data, $filePath)
    {
        $fileContent = "<?php\nreturn " . VarDumper::export($data) . ";\n";
        $self = $this;
        return Reaction\Helpers\FileHelperAsc::putContents($filePath, $fileContent, 'cwt')->then(
            function() use ($self, $filePath) {
                $self->invalidateScriptCache($filePath);
            }
        );
    }

    /**
     * Invalidates precompiled script cache (such as OPCache or APC) for the given file.
     * @param string $file the file path.
     */
    protected function invalidateScriptCache($file)
    {
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }
        if (function_exists('apc_delete_file')) {
            @apc_delete_file($file);
        }
    }

    /**
     * Saves items data into persistent storage.
     * @return ExtendedPromiseInterface
     */
    protected function saveItems()
    {
        $items = [];
        foreach ($this->items as $name => $item) {
            /* @var $item Item */
            $items[$name] = array_filter(
                [
                    'type' => $item->type,
                    'description' => $item->description,
                    'ruleName' => $item->ruleName,
                    'data' => $item->data,
                ]
            );
            if (isset($this->children[$name])) {
                foreach ($this->children[$name] as $child) {
                    /* @var $child Item */
                    $items[$name]['children'][] = $child->name;
                }
            }
        }
        return $this->saveToFile($items, $this->itemFile);
    }

    /**
     * Saves assignments data into persistent storage.
     * @return ExtendedPromiseInterface
     */
    protected function saveAssignments()
    {
        $assignmentData = [];
        foreach ($this->assignments as $userId => $assignments) {
            foreach ($assignments as $name => $assignment) {
                /* @var $assignment Assignment */
                $assignmentData[$userId][] = $assignment->roleName;
            }
        }
        return $this->saveToFile($assignmentData, $this->assignmentFile);
    }

    /**
     * Saves rules data into persistent storage.
     * @return ExtendedPromiseInterface
     */
    protected function saveRules()
    {
        $rules = [];
        foreach ($this->rules as $name => $rule) {
            $rules[$name] = serialize($rule);
        }
        return $this->saveToFile($rules, $this->ruleFile);
    }

    /**
     * {@inheritdoc}
     */
    public function getUserIdsByRole($roleName)
    {
        $result = [];
        foreach ($this->assignments as $userID => $assignments) {
            foreach ($assignments as $userAssignment) {
                if ($userAssignment->roleName === $roleName && $userAssignment->userId == $userID) {
                    $result[] = (string) $userID;
                }
            }
        }

        return resolve($result);
    }
}
