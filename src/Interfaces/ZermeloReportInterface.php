<?php
/**
 * Created by PhpStorm.
 * User: kchapple
 * Date: 4/13/18
 * Time: 12:14 PM
 */

namespace ftrotter\ZZZermelo\Interfaces;


interface ZZermeloReportInterface
{
    public function pushViewVariable($name, $value);

    public function setToken($token);

    public function isSQLPrintEnabled();
}
