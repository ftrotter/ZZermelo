<?php
/**
 * Created by PhpStorm.
 * User: kchapple
 * Date: 6/20/18
 * Time: 11:42 AM
 */

namespace ftrotter\ZZermelo\Http\Controllers;

use ftrotter\ZZermelo\Http\Requests\ZZermeloRequest;
use ftrotter\ZZermelo\Reports\Graph\CachedGraphReport;
use ftrotter\ZZermelo\Reports\Graph\GraphGenerator;

class GraphApiController
{
    public function index( ZZermeloRequest $request )
    {
        $report = $request->buildReport();

        // We use a subclass of the Standard DatabaseCache to enhance the functionality
        // To cache, not only the "main" table, but the node and link tables as well
        $cache = new CachedGraphReport( $report, zzermelo_cache_db() );
        $generatorInterface = new GraphGenerator( $cache );
        return $generatorInterface->toJson();
    }
}
