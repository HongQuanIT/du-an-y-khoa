<?php

declare(strict_types=1);

namespace App\Support\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 */
interface RepositoryInterface
{
    /** @return TModel|null */
    public function find(int|string $id): ?Model;

    /** @return TModel */
    public function findOrFail(int|string $id): Model;

    /** @return Collection<int, TModel> */
    public function all(): Collection;

    /**
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function create(array $attributes): Model;

    /**
     * @param  TModel  $model
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function update(Model $model, array $attributes): Model;

    /** @param  TModel  $model */
    public function delete(Model $model): bool;
}
