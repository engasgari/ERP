<?php

namespace App\Core\Base;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

abstract class BaseRepository
{
    protected Model $model;

    public function query(): Builder
    {
        return $this->model->newQuery();
    }

    public function find(int $id): ?Model
    {
        return $this->query()->find($id);
    }

    public function findOrFail(int $id): Model
    {
        return $this->query()->findOrFail($id);
    }

    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    public function update(Model $model, array $data): bool
    {
        return $model->update($data);
    }

    public function delete(Model $model): ?bool
    {
        return $model->delete();
    }
}