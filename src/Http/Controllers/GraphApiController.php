<?php
/**
 * Created by PhpStorm.
 * User: kchapple
 * Date: 6/20/18
 * Time: 11:42 AM
 */

namespace ftrotter\ZZZermelo\Http\Controllers;

use ftrotter\ZZZermelo\Http\Requests\ZZermeloRequest;
use ftrotter\ZZZermelo\Reports\Graph\CachedGraphReport;
use ftrotter\ZZZermelo\Reports\Graph\GraphGenerator;

class GraphApiController
{
    public function index( ZZermeloRequest $request )
    {
        $report = $request->buildReport();

        // We use a subclass of the Standard DatabaseCache to enhance the functionality
        // To cache, not only the "main" table, but the node and link tables as well
        $cache = new CachedGraphReport( $report, zermelo_cache_db() );
        $generatorInterface = new GraphGenerator( $cache );
        return $generatorInterface->toJson();
    }
}
