<?php
if ( ! function_exists('M')) {
    /**
     * 生成模型实例对象
     *
     * @param $name
     *
     * @return mixed
     */
    function M($name)
    {
        $class = '\system\model\\'.ucfirst($name);

        return \zongphp\container\Container::make($class);
    }
}
