<?php
namespace zongphp\model\build;

trait Relation {

	/**
	 * 一对一
	 *
	 * @param $class 关联模型
	 * @param int $foreignKey 关联表关联字段
	 * @param int $localKey 本模型字段
	 *
	 * @return mixed
	 */
	protected function hasOne( $class, $foreignKey = 0, $localKey = 0 ) {
		$foreignKey = $foreignKey ?: $this->table . '_' . $this->pk;
		$localKey   = $localKey ?: $this->pk;

		return ( new $class() )->where( $foreignKey, $this[ $localKey ] )->first();
	}

	/**
	 * 一对多
	 *
	 * @param $class 关联模型
	 * @param $foreignKey 关联表关联字段
	 * @param $localKey 本模型字段
	 *
	 * @return mixed
	 */
	protected function hasMany( $class, $foreignKey = null, $localKey = null ) {
		$foreignKey = $foreignKey ?: $this->table . '_' . $this->pk;
		$localKey   = $localKey ?: $this->pk;

		return ( new $class() )->where( $foreignKey, $this[ $localKey ] )->get();
	}

	/**
	 * 相对的关联
	 *
	 * @param $class
	 * @param $parentKey
	 * @param $localKey
	 *
	 * @return mixed
	 */
	protected function belongsTo( $class, $localKey = null, $parentKey = null ) {
		$instance = new $class();
		//父表
		$parentKey = $parentKey ?: $instance->getPrimaryKey();
		$localKey  = $localKey ?: $instance->getTableName() . '_' . $instance->getPrimaryKey();

		return ( new $class )->where( $parentKey, $this[ $localKey ] )->first();
	}

	/**
	 * 多对多关联
	 *
	 * @param  [string] $class       [关联类]
	 * @param  [string] $middleTabe [中间表]
	 * @param  [string] $localKey  [主表字段]
	 * @param  [string] $foreignKey  [关联表字段]
	 *
	 * @return [object]
	 */
	protected function belongsToMany( $class, $middleTable, $localKey = null, $foreignKey = null ) {
		$instance   = ( new $class );
		$localKey   = $localKey ?: $this->table . '_' . $this->pk;
		$foreignKey = $foreignKey ?: $instance->getTableName() . '_' . $instance->getPrimaryKey();
		$middle     = Db::table( $middleTable )->where( $localKey, $this[ $this->pk ] )->lists( $foreignKey );


		return $instance->whereIn( $instance->getPrimaryKey(), array_values( $middle ) )->get();
	}
}





