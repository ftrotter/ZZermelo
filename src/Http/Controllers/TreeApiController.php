<?php

namespace ftrotter\ZZermelo\Http\Controllers;

use ftrotter\ZZermelo\Http\Requests\ZermeloRequest;
use ftrotter\ZZermelo\Reports\Tree\CachedTreeReport;
use ftrotter\ZZermelo\Reports\Tree\TreeReportGenerator;
use ftrotter\ZZermelo\Reports\Tree\TreeReportSummaryGenerator;

class TreeApiController
{
    public function index( ZermeloRequest $request )
    {
        $report = $request->buildReport();
        $cache = new CachedTreeReport( $report, zermelo_cache_db() );
        $generator = new TreeReportGenerator( $cache );
        return $generator->toJson();
    }

    public function summary( ZermeloRequest $request )
    {
        $report = $request->buildReport();
        // Wrap the report in cache
        $cache = new CachedTreeReport( $report, zermelo_cache_db() );
        $generator = new TreeReportSummaryGenerator( $cache );
        return $generator->toJson();
    }
}
