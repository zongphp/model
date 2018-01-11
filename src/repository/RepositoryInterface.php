<?php
namespace zongphp\model\repository;

interface RepositoryInterface
{
    public function all($columns = ['*']);

    public function paginate($page = 15, $columns = ['*']);

    public function create(array $data);

    public function update(array $data, $id);

    public function delete($id);

    public function find($id, $columns = ['*']);

    public function findBy($field, $value, $columns = ['*']);
}
