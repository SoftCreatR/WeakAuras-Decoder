<?php
namespace SoftCreatR\WeakAuras;

final class Iter
{
    protected $matches = [];

    public function __construct($str)
    {
        preg_match_all("~(?P<ctl>\^.)(?P<data>[^^]*)~i", $str, $this->matches, PREG_SET_ORDER);
    }

    public function next()
    {
        return array_shift($this->matches);
    }
}
