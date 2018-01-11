<?php
namespace zongphp\model\repository;

/**
 * 查询规则
 * Class Rule
 *
 * @package system\repository\contracts
 */
abstract class Rule
{
    public abstract function apply($model, Repository $repository);
}
