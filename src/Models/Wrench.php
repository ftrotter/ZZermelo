<?php
/**
 * Created by PhpStorm.
 * User: kchapple
 * Date: 9/6/18
 * Time: 2:26 PM
 */

namespace ftrotter\ZZZermelo\Models;

class Wrench extends AbstractZZermeloModel
{
    protected $table = 'wrench';

    protected $fillable = ['wrench_lookup_string', 'wrench_label'];

    /*
     * Eager-load the sockets
     */
    protected $with = ['sockets'];

    public function sockets()
    {
        return $this->hasMany(Socket::class )->orderBy('socket_label');
    }
}
