<?php

function api_prefix()
{
    $api_prefix = trim( config("zzermelo.API_PREFIX"), "/ " );
    return $api_prefix;
}

function tree_api_prefix()
{
    $api_prefix = trim( config("zzermelo.TREE_API_PREFIX"), "/ " );
    return $api_prefix;
}

function tabular_api_prefix()
{
    $api_prefix = trim( config("zzermelo.TABULAR_API_PREFIX"), "/ " );
    return $api_prefix;
}

function graph_api_prefix()
{
    $api_prefix = trim( config("zzermelo.GRAPH_API_PREFIX"), "/ " );
    return $api_prefix;
}

function zzermelo_cache_db()
{
    $db = config("zzermelo.ZERMELO_CACHE_DB" );
    if ( empty($db)) {
        info("ZZermelo Cache DB not set in zzermelo.php config file.");
    }
    return $db;
}

function zzermelo_config_db()
{
    $db = config("zzermelo.ZERMELO_CONFIG_DB" );
    if ( empty($db)) {
        info("ZZermelo Config DB not set in zzermelo.php config file.");
    }
    return $db;
}

function report_path()
{
    $reportNS = config("zzermelo.REPORT_NAMESPACE" );
    $parts = explode("\\", $reportNS );
    return app_path($parts[count($parts)-1]);
}



