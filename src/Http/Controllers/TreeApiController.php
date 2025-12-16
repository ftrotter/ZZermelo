<?php

namespace ftrotter\ZZZermelo\Http\Controllers;

use ftrotter\ZZZermelo\Http\Requests\ZZermeloRequest;
use ftrotter\ZZZermelo\Reports\Tree\CachedTreeReport;
use ftrotter\ZZZermelo\Reports\Tree\TreeReportGenerator;
use ftrotter\ZZZermelo\Reports\Tree\TreeReportSummaryGenerator;

class TreeApiController
{
    public function index( ZZermeloRequest $request )
    {
        $report = $request->buildReport();
        $cache = new CachedTreeReport( $report, zermelo_cache_db() );
        $generator = new TreeReportGenerator( $cache );
        return $generator->toJson();
    }

    public function summary( ZZermeloRequest $request )
    {
        $report = $request->buildReport();
        // Wrap the report in cache
        $cache = new CachedTreeReport( $report, zermelo_cache_db() );
        $generator = new TreeReportSummaryGenerator( $cache );
        return $generator->toJson();
    }
}
