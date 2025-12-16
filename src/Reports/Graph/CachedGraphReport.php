<?php
/**
 * Created by PhpStorm.
 * User: kchapple
 * Date: 9/24/18
 * Time: 2:47 PM
 */

namespace ftrotter\ZZermelo\Reports\Graph;


use ftrotter\ZZermelo\Models\DatabaseCache;
use ftrotter\ZZermelo\Models\ZZermeloReport;
use ftrotter\ZZermelo\Models\ZZermeloDatabase;
use \DB;

class CachedGraphReport extends DatabaseCache
{
    protected $cache_db = '_zzermelo_cache';

    protected $nodes_table = null;
    protected $node_types_table = null;
    protected $node_groups_table = null;
    protected $links_table = null;
    protected $link_types_table = null;
    protected $summary_table = null;

    protected $node_types = [];
    protected $link_types = [];

    protected $visible_node_types = [];
    protected $visible_link_types = [];

    // Track which optional columns are available in the source data
    protected $available_optional_columns = [];
    
    // Define optional columns - these will only be included if present in source data
    protected const OPTIONAL_NODE_COLUMNS = [
        'latitude',
        'longitude', 
        'json_url',
        'img',
    ];

    /**
     * CachedGraphReport constructor.
     *
     * @param ZZermeloReport $report The report to be cached
     *
     * @param $connectionName The name of the Cache Database connection, which represents the cache database name, and credentials for connecting
     */
    public function __construct(AbstractGraphReport $report, $connectionName)
    {

        // create cache tables, the logic in handled in the superclass constructor, and it only generates new table if required
        // If we are rebuilding the cache in this request, the parent will generate a table with the results from the report's
        // GetSQL() function query. Then, we generate the auxillary graph cache tables below
        parent::__construct($report, $connectionName);

        // Get our cache key from parent, and use it to name all of our auxiliary graph tables
        $cache_table_name_key = $this->getKey();
        $this->nodes_table = "nodes_$cache_table_name_key";
        $this->node_types_table = "node_types_$cache_table_name_key";
        $this->node_groups_table = "node_groups_$cache_table_name_key";
        $this->links_table = "links_$cache_table_name_key";
        $this->link_types_table = "link_types_$cache_table_name_key";
        $this->summary_table = "summary_$cache_table_name_key";

	//TODO this should come from configuration...
	$this->cache_db = '_zzermelo_cache'; 

        // Only generate the aux tables (drop and re-create) if dictated by cache rules
        if ($this->getGeneratedThisRequest() === true) {
            $this->createGraphTables();
        } else {
            // If using cached data, detect available columns from existing nodes table
            $this->detectAvailableColumnsFromNodesTable();
        }
    }

    /**
     * Detect available optional columns from an existing nodes cache table.
     * This is used when the cache is not regenerated this request.
     */
    private function detectAvailableColumnsFromNodesTable(): void
    {
        $pdo = ZZermeloDatabase::connection($this->getConnectionName())->getPdo();
        
        // Get all columns from the existing nodes cache table
        $columns_sql = "SHOW COLUMNS FROM $this->cache_db.`$this->nodes_table`";
        
        try {
            $result = $pdo->query($columns_sql);
            $existing_columns = [];
            foreach ($result as $row) {
                $existing_columns[] = strtolower($row['Field']);
            }
            
            // Check each optional column
            foreach (self::OPTIONAL_NODE_COLUMNS as $column_base) {
                $node_col = "node_$column_base";
                if (in_array($node_col, $existing_columns)) {
                    $this->available_optional_columns[] = $column_base;
                }
            }
        } catch (\Exception $e) {
            // If table doesn't exist or error occurs, leave available_optional_columns empty
            $this->available_optional_columns = [];
        }
    }

    /**
     * @return null|string
     */
    public function getNodesTable()
    {
        return $this->nodes_table;
    }

    /**
     * @param null|string $nodes_table
     */
    public function setNodesTable($nodes_table)
    {
        $this->nodes_table = $nodes_table;
    }

    /**
     * @return null
     */
    public function getNodeTypesTable()
    {
        return $this->node_types_table;
    }

    /**
     * @param null $node_types_table
     */
    public function setNodeTypesTable($node_types_table)
    {
        $this->node_types_table = $node_types_table;
    }

    /**
     * @return null
     */
    public function getNodeGroupsTable()
    {
        return $this->node_groups_table;
    }

    /**
     * @param null $node_groups_table
     */
    public function setNodeGroupsTable($node_groups_table)
    {
        $this->node_groups_table = $node_groups_table;
    }

    /**
     * @return null|string
     */
    public function getLinksTable()
    {
        return $this->links_table;
    }

    /**
     * @param null|string $links_table
     */
    public function setLinksTable($links_table)
    {
        $this->links_table = $links_table;
    }

    /**
     * @return null
     */
    public function getLinkTypesTable()
    {
        return $this->link_types_table;
    }

    /**
     * @param null $link_types_table
     */
    public function setLinkTypesTable($link_types_table)
    {
        $this->link_types_table = $link_types_table;
    }

    /**
     * @return null
     */
    public function getSummaryTable()
    {
        return $this->summary_table;
    }

    /**
     * @param null $summary_table
     */
    public function setSummaryTable($summary_table)
    {
        $this->summary_table = $summary_table;
    }


    public function getVisibleNodeTypes()
    {
        return $this->visible_node_types;
    }

    public function getVisibleLinkTypes()
    {
        return $this->visible_link_types;
    }

    /**
     * Get the list of available optional columns that were found in the source data.
     * This is useful for the GraphGenerator to know which columns to include in JSON output.
     * 
     * @return array List of optional column base names (e.g., ['latitude', 'longitude', 'img'])
     */
    public function getAvailableOptionalColumns(): array
    {
        return $this->available_optional_columns;
    }

    /**
     * Check if a specific optional column is available in the data.
     * 
     * @param string $column_base_name The base name (e.g., 'latitude', 'json_url')
     * @return bool True if the column is available
     */
    public function hasOptionalColumn(string $column_base_name): bool
    {
        return in_array($column_base_name, $this->available_optional_columns);
    }

    /**
     * Detect which optional columns exist in the source cache table.
     * Checks for both source_* and target_* prefixed columns.
     * 
     * @param \PDO $pdo The PDO connection
     * @return array List of optional column base names that exist
     */
    private function detectAvailableOptionalColumns(\PDO $pdo): array
    {
        $available_columns = [];
        $table_name = $this->getTableName();
        
        // Get all columns from the source cache table
        $columns_sql = "SHOW COLUMNS FROM $this->cache_db.`$table_name`";
        $result = $pdo->query($columns_sql);
        $existing_columns = [];
        foreach ($result as $row) {
            $existing_columns[] = strtolower($row['Field']);
        }
        
        // Check each optional column - we need BOTH source_ and target_ versions to be present
        // OR we allow partial presence and use defaults for missing ones
        foreach (self::OPTIONAL_NODE_COLUMNS as $column_base) {
            $source_col = "source_$column_base";
            $target_col = "target_$column_base";
            
            // Column is available if either source or target version exists
            // (we'll use defaults for the missing one)
            if (in_array($source_col, $existing_columns) || in_array($target_col, $existing_columns)) {
                $available_columns[] = $column_base;
            }
        }
        
        return $available_columns;
    }

    /**
     * Check if a specific column exists in the source cache table.
     * 
     * @param \PDO $pdo The PDO connection
     * @param string $column_name The full column name to check
     * @return bool True if column exists
     */
    private function columnExists(\PDO $pdo, string $column_name): bool
    {
        $table_name = $this->getTableName();
        $columns_sql = "SHOW COLUMNS FROM $this->cache_db.`$table_name` LIKE '$column_name'";
        $result = $pdo->query($columns_sql);
        return $result->rowCount() > 0;
    }

    // Get the node types and link types from user input
    // TODO this is not used in this implementation, needs to be considered
    public function typesLookup()
    {
        // Perhaps this stuff should go in the JSON generation since it doesn't realate to cache, but display only
        $input_node_types = [];
        if ($this->getReport()->getInput('node_types') && is_array($this->getReport()->getInput('node_types'))) {
            $input_node_types = $this->getReport()->getInput('node_types');
        }

        $input_link_types = [];
        if ($this->getReport()->getInput('link_types') && is_array($this->getReport()->getInput('link_types'))) {
            $input_link_types = $this->getReport()->getInput('link_types');
        }

        // Go ahead to build the lookup arrays that represent the node types and link types of the graph based on
        // the node and link definitions in our report
        $fields = ZZermeloDatabase::getTableColumnDefinition($this->getTableName(), $this->connectionName);
        $node_index = 0;
        $link_index = 0;

        // These are the columns of the table to treat as Nodes and Links

        // Look at each field in our GetSQL() result table, and get all of our node types and link types, and
        // TODO Do some validation to make sure all of our nodes and links columns are actually in the table
        foreach ($fields as $field) {
            $column = $field['Name'];
            $title = ucwords(str_replace('_', ' ', $column), "\t\r\n\f\v ");
            if (ZZermeloDatabase::isColumnInKeyArray($column, $this->getReport()->getNodeTypeColumns())) {
                $subjects_found[] = $column;
                $this->node_types[$node_index] = [
                    'id' => $node_index,
                    'field' => $column,
                    'name' => $title,
                    'visible' => in_array($node_index, $input_node_types)
                ];
                $this->visible_node_types[$node_index] = $this->node_types[$node_index]['visible'];
                ++$node_index;
            }
            if (ZZermeloDatabase::isColumnInKeyArray($column, $this->getReport()->getLinkTypeColumns())) {
                $weights_found[] = $column;
                $this->link_types[$link_index] = [
                    'id' => $link_index,
                    'field' => $column,
                    'name' => $title,
                    'visible' => in_array($link_index, $input_link_types)
                ];
                $this->visible_link_types[$link_index] = $this->link_types[$link_index]['visible'];
                ++$link_index;
            }
        }

        if (!is_array($this->node_types) || empty($this->node_types)) {
            for ($i = 2, $len = count($this->node_types); $i < $len; ++$i) {
                $this->node_types[$i]['visible'] = false;
                $this->visible_node_types[$i] = false;
            }
        }
    }

    /**
     * Build the CREATE TABLE SQL for the nodes table based on available optional columns.
     * 
     * @return string The CREATE TABLE SQL statement
     */
    private function buildNodeTableCreateSql(): string
    {
        // Base columns that are always required
        $create_sql = "
CREATE TABLE $this->cache_db.$this->nodes_table (
  `id` int(11) NOT NULL,
  `node_id` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `node_name` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `node_size` bigint(20) DEFAULT NULL,
  `node_type` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `node_group` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''";
        
        // Add optional columns based on availability
        if ($this->hasOptionalColumn('latitude')) {
            $create_sql .= ",\n  `node_latitude` decimal(17,7) NOT NULL DEFAULT 0";
        }
        if ($this->hasOptionalColumn('longitude')) {
            $create_sql .= ",\n  `node_longitude` decimal(17,7) NOT NULL DEFAULT 0";
        }
        if ($this->hasOptionalColumn('json_url')) {
            $create_sql .= ",\n  `node_json_url` varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''";
        }
        if ($this->hasOptionalColumn('img')) {
            $create_sql .= ",\n  `node_img` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''";
        }
        
        $create_sql .= "\n) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        return $create_sql;
    }

    /**
     * Build the SELECT part of the source node query with optional columns.
     * Uses actual column if available, or default value if not.
     * 
     * @param \PDO $pdo The PDO connection for column existence checking
     * @param string $prefix Either 'source' or 'target'
     * @return array ['select' => string, 'group_by' => string] for use in building the full query
     */
    private function buildNodeSelectParts(\PDO $pdo, string $prefix): array
    {
        $table_name = $this->getTableName();
        
        // Base required columns
        $select_parts = [
            "`{$prefix}_id` AS node_id",
            "`{$prefix}_name` AS node_name",
            "IF(MAX(`{$prefix}_size`) > 0, MAX(`{$prefix}_size`), 50) AS node_size",
            "`{$prefix}_type` AS node_type",
            "`{$prefix}_group` AS node_group",
        ];
        
        $group_by_parts = [
            "`{$prefix}_id`",
            "`{$prefix}_name`",
            "`{$prefix}_type`",
            "`{$prefix}_group`",
        ];
        
        // Add optional columns - use actual column if exists, default if not
        if ($this->hasOptionalColumn('latitude')) {
            $col_name = "{$prefix}_latitude";
            if ($this->columnExists($pdo, $col_name)) {
                $select_parts[] = "`$col_name` AS node_latitude";
                $group_by_parts[] = "`$col_name`";
            } else {
                $select_parts[] = "0 AS node_latitude";
            }
        }
        
        if ($this->hasOptionalColumn('longitude')) {
            $col_name = "{$prefix}_longitude";
            if ($this->columnExists($pdo, $col_name)) {
                $select_parts[] = "`$col_name` AS node_longitude";
                $group_by_parts[] = "`$col_name`";
            } else {
                $select_parts[] = "0 AS node_longitude";
            }
        }
        
        if ($this->hasOptionalColumn('json_url')) {
            $col_name = "{$prefix}_json_url";
            if ($this->columnExists($pdo, $col_name)) {
                $select_parts[] = "`$col_name` AS node_json_url";
                $group_by_parts[] = "`$col_name`";
            } else {
                $select_parts[] = "'' AS node_json_url";
            }
        }
        
        if ($this->hasOptionalColumn('img')) {
            $col_name = "{$prefix}_img";
            if ($this->columnExists($pdo, $col_name)) {
                $select_parts[] = "`$col_name` AS node_img";
                $group_by_parts[] = "`$col_name`";
            } else {
                $select_parts[] = "'' AS node_img";
            }
        }
        
        return [
            'select' => implode(",\n                    ", $select_parts),
            'group_by' => implode(", ", $group_by_parts),
        ];
    }

    /**
     * Build the outer SELECT and GROUP BY for the node union query.
     * 
     * @return array ['select' => string, 'group_by' => string]
     */
    private function buildOuterNodeSelectParts(): array
    {
        $select_parts = [
            "NULL AS id",
            "node_id",
            "node_name",
            "MAX(node_size) AS node_size",
            "node_type",
            "node_group",
        ];
        
        $group_by_parts = [
            "node_id",
            "node_name",
            "node_type",
            "node_group",
        ];
        
        if ($this->hasOptionalColumn('latitude')) {
            $select_parts[] = "node_latitude";
            $group_by_parts[] = "node_latitude";
        }
        if ($this->hasOptionalColumn('longitude')) {
            $select_parts[] = "node_longitude";
            $group_by_parts[] = "node_longitude";
        }
        if ($this->hasOptionalColumn('json_url')) {
            $select_parts[] = "node_json_url";
            $group_by_parts[] = "node_json_url";
        }
        if ($this->hasOptionalColumn('img')) {
            $select_parts[] = "node_img";
            $group_by_parts[] = "node_img";
        }
        
        return [
            'select' => implode(",\n                ", $select_parts),
            'group_by' => implode(", ", $group_by_parts),
        ];
    }

    /**
     * Build the INSERT INTO nodes table SQL with dynamic optional columns.
     * 
     * @param \PDO $pdo The PDO connection
     * @return string The INSERT SQL statement
     */
    private function buildNodeInsertSql(\PDO $pdo): string
    {
        $source_parts = $this->buildNodeSelectParts($pdo, 'source');
        $target_parts = $this->buildNodeSelectParts($pdo, 'target');
        $outer_parts = $this->buildOuterNodeSelectParts();
        
        $sql = "INSERT INTO $this->cache_db.$this->nodes_table 
            SELECT  
                {$outer_parts['select']}
            FROM 
            (
                SELECT
                    {$source_parts['select']}
                
                FROM $this->cache_db.`{$this->getTableName()}`
                GROUP BY {$source_parts['group_by']}
                
                UNION 
                
                SELECT
                    {$target_parts['select']}
                
                FROM $this->cache_db.`{$this->getTableName()}`
                GROUP BY {$target_parts['group_by']} 
            ) 
            AS node_union
            GROUP BY {$outer_parts['group_by']}
";
        
        return $sql;
    }

    /**
     * This is where the graph tables are calculated.
     *
     * groups is the groups is the "group" lookup array,
     * The 'types' table is the type lookup array.
     * The 'nodes' table is the graph's nodes array, which has references to the groups and to the types.
     * The 'links' table has references to the node table and to the link_types array lookup table.
     * The config array is empty for now, cannot remember what I put there.
     * the 'summary' key has data about the graph...
     */
    private function createGraphTables()
    {
        $start_time = microtime(true);
        
        // Get PDO connection first so we can detect available columns
        $pdo = ZZermeloDatabase::connection($this->getConnectionName())->getPdo();
        
        // Detect which optional columns are available in the source data
        $this->available_optional_columns = $this->detectAvailableOptionalColumns($pdo);
        
        $sql = [];

        $sql['delete current node table'] = "DROP TABLE IF EXISTS $this->cache_db.$this->nodes_table;";

        // Build the query that builds the nodes table. This will take the source and target nodes and union them together
        // First we find all of the unique nodes in the from side of the table
        // then union them will all of the unique nodes in the two side of the table..
        // then we create a table of nodes that is the unique nodes shared between the two...

        // Use dynamic SQL generation based on available optional columns
        $sql['create the node cache table'] = $this->buildNodeTableCreateSql();
	
        //now we make rules to ensure that we have a SQL crash here if the node uniqueness rules are not followed.

        $sql['enforce uniqueness on the node_id of the table..'] = "
ALTER TABLE $this->cache_db.$this->nodes_table
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `node_id` (`node_id`);
";

        $sql['and auto increment the id'] = "
ALTER TABLE $this->cache_db.$this->nodes_table
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
";

        // Use dynamic SQL generation for INSERT based on available optional columns
        $sql['populate node cache table'] = $this->buildNodeInsertSql($pdo);

        //we do this because we need to have something that starts from zero for our JSON indexing..
        $sql["array that starts from zero"] =
            "UPDATE $this->cache_db.$this->nodes_table SET id = id - 1";

        // For all the IDs defined in the node definitions, add an index for them
        $sql["doing joins is better with indexes source side"] =
            "ALTER TABLE $this->cache_db.`{$this->getTableName()}` ADD INDEX(`source_id`);";

        $sql["doing joins is better with indexes, add to target side"] =
            "ALTER TABLE $this->cache_db.`{$this->getTableName()}` ADD INDEX(`target_id`);";

        // At this point, we've set up creation of the nodes table. We're done with nodes!
        // Now we work on Links

        // Create the link types lookup table
        $sql["drop link type table"] =
            "DROP TABLE IF EXISTS $this->cache_db.$this->link_types_table";

        // Gather all the IDs from the node definitions so we can concat them and count them
        // We wind up with a table containing all unique link types and a count of how many
        // node pairs there are of this link type
        $sql["create link type table"] =
            "CREATE TABLE $this->cache_db.$this->link_types_table
            SELECT DISTINCT
                link_type,
                COUNT(DISTINCT(CONCAT(`source_id`,`target_id`))) AS count_distinct_link
            FROM $this->cache_db.`{$this->getTableName()}`
            GROUP BY link_type
            ";

        $sql["create unique id for link type table"] =
            "ALTER TABLE $this->cache_db.$this->link_types_table ADD `id` INT(11) NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (`id`);";

        $sql["the link types table should start from zero"] = "UPDATE $this->cache_db.$this->link_types_table SET id = id - 1;";

        // Now create the links table
        // First drop the links table if it already exists
        $sql["drop links table"] = "DROP TABLE IF EXISTS $this->cache_db.$this->links_table;";

	
	$sql["create the links table with indexes"] = "
CREATE TABLE $this->cache_db.`$this->links_table` (
  `source` int(11) NOT NULL DEFAULT 0,
  `target` int(11) NOT NULL DEFAULT 0,
  `weight` decimal(15,5) NOT NULL,
  `link_type` int(11) NOT NULL DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

	$sql["primary key to prevent duplicates later on"] ="
ALTER TABLE $this->cache_db.`$this->links_table`
  ADD PRIMARY KEY (`source`,`target`,`link_type`);
";


        // Build the links table
        $sql["create links table"] =
            "INSERT IGNORE $this->cache_db.`$this->links_table` 
            SELECT 
                source_nodes.id AS `source`,
                target_nodes.id AS `target`, 
                `weight`, 
                link_types.id AS `link_type`
            FROM $this->cache_db.{$this->getTableName()} AS graph
            JOIN $this->cache_db.{$this->nodes_table} AS source_nodes 
            ON source_nodes.node_id = graph.source_id
            JOIN $this->cache_db.{$this->nodes_table} AS target_nodes 
            ON target_nodes.node_id = graph.target_id  
            JOIN $this->cache_db.{$this->link_types_table} AS link_types 
            ON link_types.link_type = graph.link_type
        ";


        //Sort the node type table...

        $sql["drop node type table"] = "DROP TABLE IF EXISTS $this->cache_db.$this->node_types_table";

        //we use the same "distinct on the results of a union of two distincts" method
        //that we used to sort the nodes... but this time we get a unique list of node types...

        $sql["create node type table"] =
            "CREATE TABLE $this->cache_db.$this->node_types_table
            SELECT 	
                node_type, 
                COUNT(DISTINCT(node_id)) AS count_distinct_node
            FROM (
                    SELECT DISTINCT 
                        source_type AS node_type,
                        source_id AS node_id
                    FROM $this->cache_db.`{$this->getTableName()}`
                UNION 
                    SELECT DISTINCT 
                        target_type AS node_type,
                        target_id AS node_id
                    FROM $this->cache_db.`{$this->getTableName()}`
                ) AS  merged_node_type
            GROUP BY node_type
	";

        $sql["create unique id for node type table"] =
            "ALTER TABLE $this->cache_db.`{$this->node_types_table}` ADD `id` INT(11) NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (`id`);";

        $sql["the node types table should start from zero"] = "UPDATE $this->cache_db.{$this->node_types_table} SET id = id - 1";

        //we use the same "distinct on the results of a union of two distincts" method
        //that we used to sort the nodes... but this time we get a unique list of node types...
        $sql["drop node group table"] = "DROP TABLE IF EXISTS $this->cache_db.{$this->node_groups_table}";

        $sql["create node group table"] =
            "CREATE TABLE $this->cache_db.{$this->node_groups_table}
            SELECT 	
                group_name, 
                COUNT(DISTINCT(node_id)) AS count_distinct_node
            FROM (
                    SELECT DISTINCT 
                        source_group AS group_name,
                        source_id AS node_id
                    FROM $this->cache_db.`{$this->getTableName()}`
                UNION 
                    SELECT DISTINCT 
                        target_type AS group_name,
                        target_id AS node_id
                    FROM $this->cache_db.`{$this->getTableName()}`
                ) AS  merged_node_type
            GROUP BY group_name";

        $sql["create unique id for node group table"] =
            "ALTER TABLE $this->cache_db.$this->node_groups_table ADD `id` INT(11) NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (`id`);";

        $sql["the node group table should start from zero"] =
            "UPDATE $this->cache_db.$this->node_groups_table SET id = id - 1;";

        $sql["drop the summary table"] = "DROP TABLE IF EXISTS $this->cache_db.$this->summary_table;";

	$sql["create the summary table with varchar to be sage"] =  "
CREATE TABLE $this->cache_db.$this->summary_table (
  `summary_key` varchar(39) NOT NULL,
  `summary_value` varchar(255) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

        $sql["create the summary table with the group count"] =
            "INSERT INTO $this->cache_db.$this->summary_table 
            SELECT 
                'group_count                            ' AS summary_key,
                COUNT(DISTINCT(group_name))  AS summary_value
            FROM $this->cache_db.$this->node_groups_table";

        $sql["add the type count"] =
            "INSERT INTO $this->cache_db.$this->summary_table
            SELECT 
                'type_count' AS summary_key,
                COUNT(DISTINCT(node_type)) AS summary_value
            FROM $this->cache_db.$this->node_types_table";

        $sql["add the node count"] =
            "INSERT INTO $this->cache_db.$this->summary_table
            SELECT 
                'nodes_count' AS summary_key,
                COUNT(DISTINCT(`id`)) AS summary_value
            FROM $this->cache_db.$this->nodes_table";

        $sql["add the edge count"] =
            "INSERT INTO $this->cache_db.$this->summary_table
            SELECT 
                'links_count' AS summary_key,
                COUNT(DISTINCT(CONCAT(source_id,target_id))) AS summary_value
            FROM $this->cache_db.`{$this->getTableName()}`";

        //loop all over the sql commands and run each one in order...
        // The connection is a DB Connection to our CACHE DATABASE using the credentials
        // The connection is created in ftrotter\ZZermelo\Models\ZZermeloDatabsse
        foreach ($sql as $this_sql) {
	    try{
            	$pdo->exec($this_sql);
	    }
	    catch( \Exception $e){
		echo "<h1>Attempting to create ZZermelo graph cache. SQL Failed. Offending SQL:</h1><pre>$this_sql</pre>";
		echo "<h1>Error Message: </h1>";
		echo "<pre>" . $e->getMessage() . "</pre>";
		//throw $e;
		exit();
	    }
        }


        $time_elapsed = microtime(true) - $start_time;

	//TODO this seems to fail regularly. Forcing the value to 1 to stabilize needs to be debugged
	$time_elapsed = 1;
	
        $processing_time_sql = "INSERT INTO $this->cache_db.$this->summary_table
            SET 
		summary_key = 'seconds_to_process',
            	summary_value = '$time_elapsed'
;
";
        $pdo->exec($processing_time_sql);
    }
}
