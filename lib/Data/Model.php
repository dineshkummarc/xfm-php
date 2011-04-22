<?php
/*
 * (c) 2010 Damien Corpataux
 *
 * LICENSE
 * This library is licensed under the GNU GPL v3.0 license,
 * accessible at http://www.gnu.org/licenses/gpl-3.0.html
 *
**/

/**
 * Model base class.
 *
 * Responsibilities:
 * - deal with database transactions
 * @package xFreemwork
**/
abstract class xModel extends xRestElement {

    var $table = 'sometablename';

    /**
     * This property is set in the constructor.
     *
     * Name of the main table:
     * i.e. the table affected by the default put(),post(),delete() methods (if not overriden).
     * @var string
     */
    var $maintable = null;

    /**
     * This property is set in the constructor.
     * Name of the model (eg. a class name 'SomeModel' becomes 'my').
     * @var string
     */
    var $name = null;

    /**
     * Params to fields mapping.
     * This mapping purpose is to abstract table fields names.
     * <code>
     * array(
     *     'id' => 'id',
     *     'name' => 'name',
     *     'example_name' => 'another_name_in_table',
     *     'shortname' => 'name_in_table'
     * )
     * </code>
     * @var array
     */
    var $mapping = array();

    /**
     * Fields validation definitions.
     * This validation puropose is to use the xValidator classes
     * to ease models validation code.
     * Also, these can be reused in xForm validation definition.
     * <code>
     * array(
     *     'name' => array(
     *         'mandatory',
     *         'string' => array(2, 50)
     *     )
     * );
     * </code>
     * @see invalids()
     * @see xValidatorStore
     * @var array
     */
    var $validation = array();

    /**
     * The primary key field names (model field names, defaults to 'id').
     * @var array
     */
    var $primary = array('id');

    /**
     * Fields (model) that accept sql constants (e.g. CURRENT_TIMESTAMP).
     * These fields value, when provided with a mysql constant or function,
     * will not be escaped and enquoted.
     * If empty, any field will accept sql constants.
     * Example array:
     * <code>
     * array('mapping_name')
     * </code>
     * @see xModel::fields_values()
     * @var array
     */
    var $constants = array();

    /**
     * Mandatory params for get operations (model fields names)
     * @var array
     */
    var $get = array();

    /**
     * Mandatory params for post operations (model fields names)
     * @var array
     */
    var $post = array();

    /**
     * Mandatory params for put operations (model fields names)
     * @var array
     */
    var $put = array();

    /**
     * Mandatory params for delete operations (model fields names)
     * @var array
     */
    var $delete = array();

    /**
     * Fields to return (model fields names).
     * Contains db fields name(s).
     * This property will be overridden with the xreturn parameter value.
     * @see Model::sql_select()
     * @var string|array
     */
    var $return = array('*');

    /**
     * Available SQL joins.
     * Array example:
     * <code>
     * array(
     *    'foreign_model_name' => 'LEFT JOIN foreign_table_name ON this_table_id = foreign_table_id'
     * )
     * </code>
     * @see xModel::sql_join()
     * @var array
     */
    var $joins = array();

    /**
     * Enabled SQL joins.
     * Contains model name(s).
     * This property will be overridden with the xjoin parameter value.
     * @see xModel::joins()
     * @var array
     */
    var $join = array();

    /**
     * Result sorting fields (model fields names).
     * Contains model fields name(s).
     * This property will be overridden with the xorder_by parameter value.
     * @see xModel::sql_order()
     * @var string|array
     */
    var $order_by = null; //'id';

    /**
     * Result sorting order.
     * Accepted values: 'ASC' or 'DESC'.
     * This property will be overridden with the xorder parameter value.
     * @see xModel::sql_order()
     * @var string
     */
    var $order = null;

    /**
     * Result group by.
     * Contains model fields name(s).
     * This property will be overridden with the xgroup_by parameter value.
     * @see xModel::sql_group()
     * @var string|array
     */
    var $group_by = null;

    /**
     * Specifies which of the fields accept HTML.
     * Example:
     * <code>
     * array('field-1', 'field-2')
     * </code>
     * @var array
     */
    var $allow_html = array();

    /**
     * Model classes should only be instanciated through the Model::load() method.
     * @see Model::load()
     */
    protected function __construct($params = null) {
        parent::__construct($params);
        // Sets the maintable name
        $this->maintable = trim(array_shift(explode(',', $this->table)));
        // Sets the model name
        $this->name = strtolower(substr(get_class($this), 0, -strlen('Model')));
        // Strip HTML tags from fields values
        foreach (array_intersect_key($this->params, $this->mapping) as $field => $value) {
            if (in_array($field, $this->allow_html)) continue;
            // Strips HTML tags in params values, dig down into array if necessary
            if (!is_array($this->params[$field])) {
                $this->params[$field] = xUtil::strip_tags($this->params[$field]);
            } else {
                foreach ($this->params[$field] as $key => $value) {
                    $this->params[$field][$key] = xUtil::strip_tags($this->params[$field][$key]);
                }
            }
        }
        // Overrides model properties from x-params
        $overrides = array(
            //'parameter name' => 'class property name',
            'xjoin' => 'join',
            'xorder' => 'order',
            'xorder_by' => 'order_by',
            'xgroup_by' => 'group_by',
            'xreturn' => 'return',
        );
        foreach ($overrides as $parameter => $property) {
            if (isset($this->params[$parameter])) $this->$property = $this->params[$parameter];
        }
    }

    /**
     * Loads and returns the model specified object.
     * @param string The model to load.
     *        e.g. item will load the models/item.php file
     *        and return an instance of the ItemModel class.
     * @return xModel
     */
    static function load($name, $params = null, $options = array()) {
        $files = array(
            str_replace(array('/', '.', '-'), '', $name)."Model" => xContext::$basepath."/models/{$name}.php"
        );
        $instance = self::load_these($files, $params);
        // Applies options
        foreach ($options as $key => $value) {
            $instance->$key = $value;
        }
        return $instance;
    }

    /**
     * Returns the model field name from the given database field name.
     * @param string The model field name.
     * @return string The databse field name.
     */
    function dbfield($modelfield) {
        $dbfield = @$this->mapping[$modelfield];
        return $dbfield ? $dbfield : $modelfield;
    }

    /**
     * Returns the database field name from the given model field name.
     * If the database field is not mapped, returns the given database field name.
     * @param string The model field name, or the database field name if not mapped.
     * @return string The databse field name.
     */
    function modelfield($dbfield) {
        $modelfield = @array_search($dbfield, $this->mapping);
        return $modelfield ? $modelfield : $dbfield;
    }

    /**
     * Returns an associative array contanining params values
     * with translated keys (e.g. keys reflect the db fields names).
     * Return array structure:
     * <code>
     * array(
     *     'db_fieldname_1' => array(
     *         'value' => 'some value',
     *         'wildcard' => false
     *      ),
     *      'db_fieldname_2' => array(
     *         'value' => '%some value%',
     *         'wildcard' => true
     *      ),
     *      ...
     * )
     * </code>
     * - Only mapped fields are taken into account.
     * - Wildcard filters patterns are applied to params values if applicable.
     * @param bool If true, applies the filters pattern be applied to value
     * @return array
     */
    function fields_values($no_primary = false) {
        $mapping = array();
        foreach ($this->params as $paramname => $paramvalue) {
            if (!array_key_exists($paramname, $this->mapping)) continue;
            // If applicable, skips field if it is a primary key field
            if ($no_primary && in_array($paramname, $this->primary)) continue;
            $field = $this->mapping[$paramname];
//            if ($paramvalue === '') $paramvalue = 'NULL';
            $mapping[$field] = $paramvalue;
        }
        return $mapping;
    }

    /**
    * Returns an array of modelfield => value containing the subset of fields that belong to the given foreign model name.
    * @param string|array The foreign model(s) name(s). If not given, uses the current $join property value.
    * @param boolean True to return fields with their model name as a prefix (defaults to false).
    * @return array
    */
    function foreign_fields_values($foreign_models_names = null) {
        // Manages function argument default values
        if (is_null($foreign_models_names)) $foreign_models_names = xUtil::arrize($this->join);
        else $foreign_models_names = xUtil::arrize($foreign_models_names);
        // Creates the foreign_fields_values array for each foreign model name
        $foreign_fields_values = array();
        foreach ($foreign_models_names as $foreign_model_name) {
            // Determines fields & values beloning to foreign model:
            $local_model = xModel::load($this->name, $this->params);
            $foreign_model_name = $foreign_model_name;
            $foreign_model = xModel::load($foreign_model_name);
            // Creates the foreign_fields_values array
            foreach ($local_model->foreign_mapping() as $local_foreign_modelfield => $local_foreign_dbfield) {
                foreach ($foreign_model->mapping as $foreign_modelfield => $foreign_dbfield) {
                    $prefixed_field_name = "{$foreign_model_name}_{$foreign_modelfield}";
                    if ($local_foreign_modelfield == $prefixed_field_name && isset($local_model->params[$local_foreign_modelfield])) {
                        $foreign_fields_values[$foreign_modelfield] = $local_model->params[$local_foreign_modelfield];
                    }
                }
            }
        }
        return $foreign_fields_values;
    }

    /**
     * Returns the model mapping, including the foreign models mapping according the xjoin defined in parameters.
     * @return array
     */
    function foreign_mapping() {
        $joins = xUtil::filter_keys($this->joins, xUtil::arrize($this->join));
        $foreign_mapping = array();
        foreach ($joins as $model_name => $join) {
            $model = xModel::load($model_name);
            foreach($model->mapping as $model_field => $db_field) {
                $foreign_mapping["{$model_name}_{$model_field}"] = "{$model->maintable}.{$db_field}";
            }
        }
        return $foreign_mapping;
    }

    /**
     * Checks given params values and returns an array containing
     * the invalid params (fields) as key, and true as value.
     * @param array If given, limits validation to the given $fields.
     *              {@see xValidatorStore::invalids()}
     * @return array
     */
    function invalids($fields = array()) {
        $validator = new xValidatorStore($this->validation, $this->params);
        return $validator->invalids($fields);
    }

    /**
     * Implements the REST get method to access data.
     * Issues a SELECT and returns the result
     * as an associative array.
     * @see xModel::query()
     * @return array
     */
    function get() {
        throw new xException('Not implemented', 501);
    }

    /**
     * Implements the REST post method to access data.
     * Issues a UPDATE and returns the result
     * as an associative array.
     * @see xModel::query()
     * @return array
     */
    function post() {
        throw new xException('Not implemented', 501);
    }

    /**
     * Implements the REST put method to access data.
     * Issues a INSERT and returns the result
     * as an associative array.
     * @see xModel::query()
     * @return array
     */
    function put() {
        throw new xException('Not implemented', 501);
    }

    /**
     * Implements the REST delete method to access data.
     * Issues a DELETE and returns the result
     * as an associative array.
     * @see xModel::query()
     * @return array
     */
    function delete() {
        throw new xException('Not implemented', 501);
    }

    /**
     * Issues a COUNT and returns the result
     * @see xModel::query()
     * @return int
     */
    function count() {
        throw new xException('Not implemented', 501);
    }

    /**
     * Return the given value, escaped and quoted.
     * If the field name (modelfield) is given and the
     * field is listed in self::constants array,
     * and the value is a sql constant,
     * returns the value unquoted and unescaped.
     * @param string The value to escape and enquote
     * @param string Optional field name (modelfield)
     * @return string
     */
    abstract function escape($value, $field = null);

    /**
     * Returns the allowed SQL constants (eg. CURRENT_TIMESTAMP).
     * @return array
     */
    abstract function sql_constants();

    /**
     * Returns a default SQL SELECT clause.
     * @return string
     */
    abstract function sql_select();

    /**
     * Returns a default SQL FROM clause.
     * @return string
     */
    abstract function sql_from();

    /**
     * Returns a default SQL WHERE clause.
     * @return string
     */
    abstract function sql_where($primary_only = false);

    /**
     * Returns a default SQL JOIN clause to be used as a join.
     * @return string
     */
    abstract function sql_join();

    /**
     * Returns a default SQL ORDER clause.
     * @return string
     */
    abstract function sql_order();

    /**
     * Returns a default SQL GROUP BY clause.
     * @return string
     */
    abstract function sql_group();

    /**
     * Returns default SQL LIMIT and/or OFFSET clause(s).
     * @return string
     */
    abstract function sql_limit();

    /**
     * Exectues the given sql and returns its query result.
     *  - If the result is empty, an empty array is returned.
     *  - If the result is not empty, an 2 dimensional associative array
     *    with mapped array keys containing the result rows is returned.
     *  - If the query didn't return row results
     *    (e.g. insert, update and delete queries),
     *    an informational array is returned, containg
     *     - the last insert id,
     *     - the number of affected rows,
     *     - additional mysql information,
     *     - and the raw mysql result.
     * @param string The SQL statement to execute.
     * @return array
     */
    abstract function query($sql);

    static function q($sql) {
        $driver = xContext::$config->db->driver;
        /* Should be:
         * $model_class = "xModel{$driver}";
         * $return $model_class::q($sql);
         * But for PHP 5.3 compatibility, has to be:
         */
         switch ($driver) {
             case 'postgres': return xModelPostgres::q($sql);
             default:
             case 'mysql': return xModelMysql::q($sql);
         }
    }
}

?>