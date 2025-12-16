<?php

namespace ftrotter\ZZZermelo\Reports\Tabular;

use ftrotter\ZZZermelo\Interfaces\CacheInterface;
use ftrotter\ZZZermelo\Interfaces\GeneratorInterface;
use ftrotter\ZZZermelo\Models\ZZermeloReport;
use ftrotter\ZZZermelo\Exceptions\InvalidDatabaseTableException;
use ftrotter\ZZZermelo\Exceptions\InvalidHeaderFormatException;
use ftrotter\ZZZermelo\Exceptions\InvalidHeaderTagException;
use ftrotter\ZZZermelo\Exceptions\UnexpectedHeaderException;
use ftrotter\ZZZermelo\Exceptions\UnexpectedMapRowException;
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
