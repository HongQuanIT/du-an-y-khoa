<?php

declare(strict_types=1);

namespace App\Support\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Base Eloquent repository. Use repositories only where they earn their keep
 * (complex/reused queries) — simple lookups can call the model directly.
 *
 * @template TModel of Model
 *
 * @implements RepositoryInterface<TModel>
 */
abstract class EloquentRepository implements RepositoryInterface
{
    /**
     * Fully-qualified model class name.
     *
     * @return class-string<TModel>
     */
    abstract protected function model(): string;

    /**
     * Fresh query builder for the underlying model.
     *
     * @return Builder<TModel>
     */
    public function query(): Builder
    {
        return $this->model()::query();
    }

    public function find(int|string $id): ?Model
    {
        return $this->query()->find($id);
    }

    public function findOrFail(int|string $id): Model
    {
        return $this->query()->findOrFail($id);
    }

    public function all(): Collection
    {
        return $this->query()->get();
    }

    public function create(array $attributes): Model
    {
        return $this->query()->create($attributes);
    }

    public function update(Model $model, array $attributes): Model
    {
        $model->fill($attributes)->save();

        return $model;
    }

    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }
}
