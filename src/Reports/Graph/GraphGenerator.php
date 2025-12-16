<?php

namespace ftrotter\ZZermelo\Reports\Graph;

use ftrotter\ZZermelo\Interfaces\CacheInterface;
use ftrotter\ZZermelo\Models\AbstractGenerator;
use ftrotter\ZZermelo\Models\DatabaseCache;
use ftrotter\ZZermelo\Models\ZZermeloDatabase;
use DB;

class GraphGenerator extends AbstractGenerator
{
    protected $cache = null;
    protected $report = null;
    protected $cache_db = '_zzermelo_cache'; //TODO this should be set in config

    public function __construct(CachedGraphReport $cache)
    {
        $this->cache = $cache;
        $this->report = $cache->getReport();
    }

    /**
     * GraphModelJson
     * Retrieve the nodes and links array to be used with graph from the appropriate cached table
     *
     * @return array
     */
    public function toJson(): array
    {

	$pdo = ZZermeloDatabase::connection($this->cache->getConnectionName())->getPdo();

        $report_description = $this->report->getReportDescription();
        $report_name = $this->report->getReportName();
	$cache_db = '_zzermelo_cache'; //should be coming from config TODO

        //lets read in the node types

        $node_types_sql = "
SELECT 
	CAST(CONVERT(id USING utf8) AS binary) AS my_index,
	CAST(CONVERT(node_type USING utf8) AS binary) AS id,
	CAST(CONVERT(node_type USING utf8) AS binary) AS label,
	0 AS is_img,
	'' AS img_stub,
	CAST(CONVERT(count_distinct_node USING utf8) AS binary) AS type_count
FROM $this->cache_db.{$this->cache->getNodeTypesTable()}	
";
        //lets load the node_types from the database...
        $node_types = [];
	$node_types_result = $pdo->query($node_types_sql);
	$node_types_result->setFetchMode(\PDO::FETCH_OBJ);
        foreach ($node_types_result as $this_row) {

            //handle the differeces between json and mysql/php here for is_img
            if ($this_row->is_img) {
                $is_img = false;
            } else {
                $is_img = $this_row->is_img;
            }

            $node_types[$this_row->my_index] = [
                'id' => $this_row->id,
                'label' => $this_row->label,
                'is_img' => $is_img,
                'img_stub' => $this_row->img_stub,
                'type_count' => $this_row->type_count,
            ];
        }

        //lets read in the link types

        $link_types_sql = "
SELECT 
	CAST(CONVERT(id USING utf8) AS binary) AS my_index,
	CAST(CONVERT(link_type USING utf8) AS binary) AS label,
	CAST(CONVERT(count_distinct_link USING utf8) AS binary) AS link_type_count
FROM $this->cache_db.{$this->cache->getLinkTypesTable()}	
";
        //lets load the link_types from the database...
        $link_types = [];
	$link_types_result = $pdo->query($link_types_sql);
	$link_types_result->setFetchMode(\PDO::FETCH_OBJ);
        foreach ($link_types_result as $this_row) {

            $link_types[$this_row->my_index] = [
                'id' => $this_row->label,
                'label' => $this_row->label,
                'link_type_count' => $this_row->link_type_count,
            ];
        }

        //lets read in the link types

        $group_sql = "
SELECT 
	CAST(CONVERT(id USING utf8) AS binary) AS my_index,
	CAST(CONVERT(group_name USING utf8) AS binary) AS id,
	CAST(CONVERT(group_name USING utf8) AS binary) AS name,
	CAST(CONVERT(count_distinct_node USING utf8) AS binary) AS group_count
FROM $this->cache_db.{$this->cache->getNodeGroupsTable()}	
";

        //lets load the link_types from the database...
        $node_groups = [];
	$node_groups_result = $pdo->query($group_sql);
	$node_groups_result->setFetchMode(\PDO::FETCH_OBJ);
        foreach ($node_groups_result as $this_row) {

            $node_groups[$this_row->my_index] = [
                'id' => $this_row->id,
                'name' => $this_row->name,
                'group_count' => $this_row->group_count,
            ];
        }

        // Build the nodes SQL dynamically based on available optional columns
        // Start with required columns
        $optional_select_columns = "";
        
        // Check which optional columns are available and add them to the SELECT
        $has_latitude = $this->cache->hasOptionalColumn('latitude');
        $has_longitude = $this->cache->hasOptionalColumn('longitude');
        $has_json_url = $this->cache->hasOptionalColumn('json_url');
        $has_img = $this->cache->hasOptionalColumn('img');
        
        if ($has_latitude) {
            $optional_select_columns .= "\n\tCAST(CONVERT(`node_latitude` USING utf8) AS binary) AS latitude,";
        }
        if ($has_longitude) {
            $optional_select_columns .= "\n\tCAST(CONVERT(`node_longitude` USING utf8) AS binary) AS longitude,";
        }
        if ($has_json_url) {
            $optional_select_columns .= "\n\tCAST(CONVERT(`node_json_url` USING utf8) AS binary) AS json_url,";
        }
        if ($has_img) {
            $optional_select_columns .= "\n\tCAST(CONVERT(node_img USING utf8) AS binary) AS img,";
        }

        $nodes_sql = "
SELECT 
	CAST(CONVERT(`node_name` USING utf8) AS binary) AS name,$optional_select_columns
	CAST(CONVERT(groups.id USING utf8) AS binary) AS `group`,
	CAST(CONVERT(node_size USING utf8) AS binary) AS size,
	CAST(CONVERT(types.id USING utf8) AS binary) AS `type`,
	CAST(CONVERT(`node_id` USING utf8) AS binary) AS id,
	0 AS weight_sum,
	0 AS degree,
	CAST(CONVERT(nodes.id USING utf8) AS binary) AS my_index
FROM $this->cache_db.{$this->cache->getNodesTable()} AS nodes
LEFT JOIN $this->cache_db.{$this->cache->getNodeGroupsTable()} AS groups ON 
	groups.group_name COLLATE utf8mb4_unicode_ci =
    	node_group COLLATE utf8mb4_unicode_ci
LEFT JOIN $this->cache_db.{$this->cache->getNodeTypesTable()} AS types ON 
	types.node_type COLLATE utf8mb4_unicode_ci =
    	nodes.node_type COLLATE utf8mb4_unicode_ci
ORDER BY nodes.id ASC
";
        //lets load the nodes from the database...
        $nodes = [];

/*
TODO 
The following line results in
SQLSTATE[HY000]: General error: 1267 Illegal mix of collations (utf8mb4_general_ci,IMPLICIT) and (utf8mb4_unicode_ci,IMPLICIT) for operation '='
*/

	$nodes_result = $pdo->query($nodes_sql);
	$nodes_result->setFetchMode(\PDO::FETCH_OBJ);
        foreach ($nodes_result as $this_row) {

            // Build node array with required columns first
            $node_data = [
                'name' => $this_row->name,
                'short_name' => substr($this_row->name, 0, 50),
                'group' => (int)$this_row->group,
                'size' => (int)$this_row->size,
                'type' => (int)$this_row->type,
                'id' => $this_row->id,
                'weight_sum' => (int)$this_row->weight_sum,
                'degree' => (int)$this_row->degree,
                'my_index' => (int)$this_row->my_index,
            ];
            
            // Add optional columns only if they are available in the data
            if ($has_latitude) {
                $node_data['latitiude'] = $this_row->latitude; // Note: typo preserved for backwards compatibility
            }
            if ($has_longitude) {
                $node_data['longitude'] = $this_row->longitude;
            }
            if ($has_json_url) {
                $node_data['json_url'] = $this_row->json_url;
            }
            if ($has_img) {
                if (is_null($this_row->img)) {
                    $node_data['img'] = false;
                } else {
                    $node_data['img'] = $this_row->img;
                }
            }

            $nodes[(int) $this_row->my_index] = $node_data;
        }

	//nodes are built, but we want to make sure that it turns into an array in the json rather than object..
	$nodes = array_values($nodes); //should return  a zero indexed array. not sure why this converts it to an array.. but it does....

        // Retrieve the links from the DB
        $links_sql = "SELECT * FROM $this->cache_db.`{$this->cache->getLinksTable()}`";
        $links = [];
	$links_result = $pdo->query($links_sql);
	$links_result->setFetchMode(\PDO::FETCH_OBJ);
        foreach ($links_result as $this_row) {

            $links[] = [
                'source' => $this_row->source,
                'target' => $this_row->target,
                'weight' => $this_row->weight,
                'link_type' => $this_row->link_type,
            ];
        }

        //lets export the summary data on the graph
        $summary_sql = "
            SELECT 
                summary_key,
                summary_value
            FROM $this->cache_db.{$this->cache->getSummaryTable()}";

        $summary = [];
	$summary_result = $pdo->query($summary_sql);
	$summary_result->setFetchMode(\PDO::FETCH_OBJ);
        foreach ($summary_result as $this_row) {
            $summary[][$this_row->summary_key] = $this_row->summary_value;
        }

        //now we put it all together to return the results...
        return [
            'careset_name' => 'For backwards compatibility',
            'careset_code' => '1112223334',
            'Report_Name' => $report_name,
            'Report_Description' => $report_description,
            'Report_Key' => $this->cache->getKey(),
            'summary' => $summary,
            'config' => [], //not implemented..
            'groups' => $node_groups,
            'types' => $node_types,
            'link_types' => $link_types,
            'nodes' => $nodes,
            'links' => $links,
        ];
    }


}
