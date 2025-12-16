<?php

namespace ftrotter\ZZZermelo\Http\Controllers;

use ftrotter\ZZZermelo\Http\Requests\CardsReportRequest;
use ftrotter\ZZZermelo\Http\Requests\ZZermeloRequest;
use ftrotter\ZZZermelo\Models\DatabaseCache;
use ftrotter\ZZZermelo\Reports\Tabular\ReportGenerator;
use ftrotter\ZZZermelo\Reports\Tabular\ReportSummaryGenerator;

class CardsApiController
{
    public function index( ZZermeloRequest $request )
    {
        $report = $request->buildReport();
        $cache = new DatabaseCache( $report, zermelo_cache_db() );
        $generator = new ReportGenerator( $cache );
        return $generator->toJson();
    }

    public function summary( ZZermeloRequest $request )
    {
        $report = $request->buildReport();
        // Wrap the report in cache
        $cache = new DatabaseCache( $report, zermelo_cache_db() );
        $generator = new ReportSummaryGenerator( $cache );
        return $generator->toJson();
    }
}
