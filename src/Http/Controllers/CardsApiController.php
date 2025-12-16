<?php

namespace ftrotter\ZZermelo\Http\Controllers;

use ftrotter\ZZermelo\Http\Requests\CardsReportRequest;
use ftrotter\ZZermelo\Http\Requests\ZZermeloRequest;
use ftrotter\ZZermelo\Models\DatabaseCache;
use ftrotter\ZZermelo\Reports\Tabular\ReportGenerator;
use ftrotter\ZZermelo\Reports\Tabular\ReportSummaryGenerator;

class CardsApiController
{
    public function index( ZZermeloRequest $request )
    {
        $report = $request->buildReport();
        $cache = new DatabaseCache( $report, zzermelo_cache_db() );
        $generator = new ReportGenerator( $cache );
        return $generator->toJson();
    }

    public function summary( ZZermeloRequest $request )
    {
        $report = $request->buildReport();
        // Wrap the report in cache
        $cache = new DatabaseCache( $report, zzermelo_cache_db() );
        $generator = new ReportSummaryGenerator( $cache );
        return $generator->toJson();
    }
}
