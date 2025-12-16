<?php
/**
 * Created by PhpStorm.
 * User: kchapple
 * Date: 9/6/18
 * Time: 2:26 PM
 */

namespace ftrotter\ZZermelo\Models;


use Illuminate\Database\Eloquent\Model;

abstract class AbstractZZermeloModel extends Model
{
    protected $connection = null;

    public function __construct( array $attributes = [] )
    {
        parent::__construct( $attributes );

        // We use the zzermelo config DB for our "in-house" models
        $this->connection = zzermelo_config_db();
    }
}