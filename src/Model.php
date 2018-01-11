<?php namespace zongphp\model;

use ArrayAccess;
use Carbon\Carbon;
use zongphp\arr\Arr;
use zongphp\collection\Collection;
use zongphp\db\Db;
use zongphp\db\Query;
use zongphp\model\build\ArrayIterator;
use zongphp\model\build\Auto;
use zongphp\model\build\Filter;
use zongphp\model\build\Relation;
use zongphp\model\build\Validate;
use zongphp\response\Response;
use Iterator;

class Model implements ArrayAccess, Iterator
{
    use ArrayIterator, Relation, Validate, Auto, Filter;

    //----------自动验证----------
    //有字段时验证
    const EXIST_VALIDATE = 1;
    //值不为空时验证
    const NOT_EMPTY_VALIDATE = 2;
    //必须验证
    const MUST_VALIDATE = 3;
    //值是空时验证
    const EMPTY_VALIDATE = 4;
    //不存在字段时处理
    const NOT_EXIST_VALIDATE = 5;
    //----------自动完成----------
    //有字段时验证
    const EXIST_AUTO = 1;
    //值不为空时验证
    const NOT_EMPTY_AUTO = 2;
    //必须验证
    const MUST_AUTO = 3;
    //值是空时验证
    const EMPTY_AUTO = 4;
    //不存在字段时处理
    const NOT_EXIST_AUTO = 5;
    //----------自动过滤----------
    //有字段时验证
    const EXIST_FILTER = 1;
    //值不为空时验证
    const NOT_EMPTY_FILTER = 2;
    //必须验证
    const MUST_FILTER = 3;
    //值是空时验证
    const EMPTY_FILTER = 4;
    //不存在字段时处理
    const NOT_EXIST_FILTER = 5;
    //--------处理时机/自动完成&自动验证共用
    //插入时处理
    const MODEL_INSERT = 1;
    //更新时处理
    const MODEL_UPDATE = 2;
    //全部情况下处理
    const MODEL_BOTH = 3;
    //允许填充字段
    protected $allowFill = [];
    //禁止填充字段
    protected $denyFill = [];
    //模型数据
    protected $data = [];
    //读取字段
    protected $fields = [];
    //构建数据
    protected $original = [];
    //数据库连接
    protected $connect;
    //表名
    protected $table;
    //表主键
    protected $pk;
    //字段映射
    protected $map = [];
    //时间操作
    protected $timestamps = false;
    //数据库驱动
    protected $db;

    public function __construct()
    {
        if ($this->table) {
            $this->init();
        }
    }

    /**
     * 初始化模型数据
     *
     * @return $this
     */
    protected function init()
    {
        $this->setTable($this->table);
        $this->setDb(Db::table($this->table));
        $this->db->setModel($this);
        $this->setPk($this->db->getPrimaryKey());

        return $this;
    }

    /**
     * 设置表名
     *
     * @param $table
     *
     * @return $this
     */
    protected function setTable($table)
    {
        //设置表名
        if (empty($table)) {
            $model = basename(str_replace('\\', '/', get_class($this)));

            $table = strtolower(
                trim(preg_replace('/([A-Z])/', '_\1\2', $model), '_')
            );
        }
        $this->table = $table;

        return $this;
    }

    /**
     * 获取表名
     *
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * 设置数据库连接
     *
     * @param mixed $db
     */
    public function setDb($db)
    {
        $this->db = $db;
    }

    /**
     * 获取主键
     *
     * @return mixed
     */
    public function getPk()
    {
        return $this->pk;
    }

    /**
     * 设置主键
     *
     * @param mixed $pk
     */
    public function setPk($pk)
    {
        $this->pk = $pk;
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * 设置data 记录信息属性
     *
     * @param array $data
     *
     * @return $this
     */
    public function setData(array $data)
    {
        $this->data   = array_merge($this->data, $data);
        $this->fields = $this->data;
        $this->getFormatAttribute();


        return $this;
    }

    /**
     * 用于读取数据成功时的对字段的处理后返回
     *
     * @param $field
     *
     * @return mixed
     */
    protected function getFormatAttribute()
    {
        foreach ($this->fields as $name => $val) {
            $n      = preg_replace_callback('/_([a-z]+)/', function ($v) {
                return strtoupper($v[1]);
            }, $name);
            $method = "get".ucfirst($n)."AtAttribute";
            if (method_exists($this, $method)) {
                $this->fields[$name] = $this->$method($val);
            }
        }

        return $this->fields;
    }

    /**
     * 对象数据转为数组
     *
     * @return array
     */
    final public function toArray()
    {
        $data = $this->data;
        foreach ($data as $k => $v) {
            if (is_object($v) && method_exists($v, 'toArray')) {
                $data[$k] = $v->toArray();
            }
        }

        return $data;
    }

    /**
     * 自动填充数据处理
     *
     * @param array $data
     *
     * @throws \Exception
     */
    final private function fieldFillCheck(array $data)
    {
        if (empty($this->allowFill) && empty($this->denyFill)) {
            return;
        }
        //允许填充的数据
        if ( ! empty($this->allowFill) && $this->allowFill[0] != '*') {
            $data = Arr::filterKeys($data, $this->allowFill, 0);
        }
        //禁止填充的数据
        if ( ! empty($this->denyFill)) {
            if ($this->denyFill[0] == '*') {
                $data = [];
            } else {
                $data = Arr::filterKeys($data, $this->denyFill, 1);
            }
        }
        $this->original = array_merge($this->original, $data);
    }

    /**
     * 批量设置做准备数据
     *
     * @return $this
     */
    final private function formatFields()
    {
        //更新时设置
        if ($this->action() == self::MODEL_UPDATE) {
            $this->original[$this->pk] = $this->data[$this->pk];
//            $this->original            = array_merge($this->data, $this->original);
        }
        //字段时间
        if ($this->timestamps === true) {
            $this->original['updated_at'] = Carbon::now(new \DateTimeZone('PRC'));
            //更新时间设置
            if ($this->action() == self::MODEL_INSERT) {
                $this->original['created_at'] = Carbon::now(new \DateTimeZone('PRC'));
            }
        }

        return $this;
    }

    /**
     * 动作类型
     *
     * @return int
     */
    final public function action()
    {
        return empty($this->data[$this->pk]) ? self::MODEL_INSERT : self::MODEL_UPDATE;
    }

    /**
     * 更新模型的时间戳
     *
     * @return bool
     */
    final public function touch()
    {
        if ($this->action() == self::MODEL_UPDATE && $this->timestamps) {
            $data = ['updated_at' => Carbon::now('PRC')];

            return $this->db->where($this->pk, $this->data[$this->pk])->update($data);
        }

        return false;
    }

    /**
     * 更新或添加数据
     *
     * @param array $data 批量添加的数据
     *
     * @return bool
     * @throws \Exception
     */
    final public function save(array $data = [])
    {
        //自动填充数据处理
        $this->fieldFillCheck($data);
        //自动过滤
        $this->autoFilter();
        //自动完成
        $this->autoOperation();
        //处理时期字段
        $this->formatFields();
        if ($this->action() == self::MODEL_UPDATE) {
            $this->original = array_merge($this->data, $this->original);
        }
        //自动验证
        if ( ! $this->autoValidate()) {
            return false;
        }
        //更新条件检测
        $res = null;
        switch ($this->action()) {
            case self::MODEL_UPDATE:
                if ($res = $this->db->update($this->original)) {
                    $this->setData($this->db->find($this->data[$this->pk]));
                }
                break;
            case self::MODEL_INSERT:
                if ($res = $this->db->insertGetId($this->original)) {
                    if (is_numeric($res) && $this->pk) {
                        $this->setData($this->db->find($res));
                    }
                }
                break;
        }
        $this->original = [];

        return $res ? $this : false;
    }

    /**
     * 找不到时显示404页面
     *
     * @param int $id 主键
     *
     * @return mixed
     */
    final static public function findOrFail($id)
    {
        $res = self::find($id);
        if (empty($res)) {
            die(Response::_404());
        }

        return $res;
    }

    /**
     * 存在编号时根据主键编号创建模型
     * 不正在是new 新模型
     *
     * @param int $id 主键编号
     *
     * @return \zongphp\model\Model
     */
    final static public function findOrCreate($id = 0)
    {
        $model = ! empty($id) && is_numeric($id) ? static::find($id) : new static();
        if (empty($model)) {
            return new static();
        }

        return $model;
    }

    /**
     * 删除数据
     *
     * @return bool
     */
    final public function destory()
    {
        //没有查询参数如果模型数据中存在主键值,以主键值做删除条件
        if ( ! empty($this->data[$this->pk])) {
            if ($this->db->delete($this->data[$this->pk])) {
                $this->setData([]);

                return true;
            }
        }

        return false;
    }

    /**
     * 获取模型值
     *
     * @param $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        if (isset($this->fields[$name])) {
            return $this->fields[$name];
        }
        //关键方法获取
        if (method_exists($this, $name)) {
            return $this->$name();
        }
    }

    /**
     * 设置模型数据值
     *
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        $this->original[$name] = $value;
        $this->data[$name]     = $value;
    }

    /**
     * 魔术方法
     *
     * @param $method
     * @param $params
     *
     * @return mixed
     */
    public function __call($method, $params)
    {
        $res = call_user_func_array([$this->db, $method], $params);

        return $this->returnParse($method, $res);
    }

    /**
     * 调用数据驱动方法
     *
     * @param $method
     * @param $params
     *
     * @return mixed
     */
    public static function __callStatic($method, $params)
    {
        return call_user_func_array([new static(), $method], $params);
    }

    protected function returnParse($method, $result)
    {
        if ( ! empty($result)) {
            switch (strtolower($method)) {
                case 'find':
                case 'first':
                    $instance = new static();

                    return $instance->setData($result);
                case 'get':
                case 'paginate':
                    $Collection = Collection::make([]);
                    foreach ($result as $k => $v) {
                        $instance       = new static();
                        $Collection[$k] = $instance->setData($v);
                    }

                    return $Collection;
                default:
                    /**
                     * 返回值为查询构造器对象时
                     * 返回模型实例
                     */
                    if ($result instanceof Query) {
                        return $this;
                    }
            }
        }

        return $result;
    }
}
