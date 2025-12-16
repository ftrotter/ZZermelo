<?php

namespace ftrotter\ZZermelo\Reports\Tabular;

use ftrotter\ZZermelo\Interfaces\CacheInterface;
use ftrotter\ZZermelo\Interfaces\GeneratorInterface;
use ftrotter\ZZermelo\Models\ZermeloReport;
use ftrotter\ZZermelo\Exceptions\InvalidDatabaseTableException;
use ftrotter\ZZermelo\Exceptions\InvalidHeaderFormatException;
use ftrotter\ZZermelo\Exceptions\InvalidHeaderTagException;
use ftrotter\ZZermelo\Exceptions\UnexpectedHeaderException;
use ftrotter\ZZermelo\Exceptions\UnexpectedMapRowException;
use \DB;

class ReportSummaryGenerator extends ReportGenerator implements GeneratorInterface
{

    public function toJson()
    {
        return [
            'Report_Name' => $this->cache->getReport()->GetReportName(),
            'Report_Description' => $this->cache->getReport()->GetReportDescription(),
            'selected-data-option' => $this->cache->getReport()->getParameter( 'data-option' ),
            'columns' => $this->runSummary(),
            'cache_meta_generated_this_request' => $this->cache->getGeneratedThisRequest(),
            'cache_meta_last_generated' => $this->cache->getLastGenerated(),
            'cache_meta_expire_time' => $this->cache->getExpireTime(),
            'cache_meta_cache_enabled' => $this->cache->getReport()->isCacheEnabled()
        ];
    }

    public function runSummary()
    {
        return $this->getHeader(true);
    }
}
