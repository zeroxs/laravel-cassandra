<?php

namespace Hey\Lacassa\Eloquent;

use Cassandra\Rows;
use Hey\Lacassa\Collection;
use Hey\Lacassa\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\RelationNotFoundException;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class Builder extends EloquentBuilder
{
    public $allowFiltering = true;

    /**
     * Create a collection of models from plain arrays.
     *
     * @param  \Cassandra\Rows  $rows
     *
     * @return Collection
     */
    public function hydrateRows(Rows $rows)
    {
        $instance = $this->model->newInstance();

        return $instance->newCassandraCollection($rows);
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array  $columns
     *
     * @return Collection
     *
     * @throws \Exception
     */
    public function getPage($columns = ['*'])
    {
        $builder = $this->applyScopes();

        return $builder->getModelsPage($columns);
    }

    /**
     * Get the hydrated models without eager loading.
     *
     * @param  array  $columns
     *
     * @return Collection
     *
     * @throws \Exception
     */
    public function getModelsPage($columns = ['*'])
    {
        $results = $this->query->getPage($columns);

        if ($results instanceof Collection) {
            $results = $results->getRows();
        } elseif (!$results instanceof Rows) {
            throw new \Exception('Invalid type of getPage response. Expected Hey\Lacassa\Collection or Cassandra\Rows');
        }

        return $this->model->hydrateRows($results);
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param array $columns
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     *
     * @throws \Exception
     */
    public function get($columns = ['*'])
    {
        $builder = $this->applyScopes();
        $models = $builder->getModels($columns);

        if ($models->count() > 0) {
            $models = $this->model->newCollection(
                $builder->eagerLoadRelations($models->all())
            );

        }

        return $models;
    }

    /**
     * Get the hydrated models without eager loading.
     *
     * @param  array  $columns
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     *
     * @throws \Exception
     */
    public function getModels($columns = ['*'])
    {
        $results = $this->query->get($columns);

        if ($results instanceof Collection) {
            $results = $results->getRows();
        } elseif (!$results instanceof Rows) {
            throw new \Exception('Invalid type of getPage response. Expected Hey\Lacassa\Collection or Cassandra\Rows');
        }

        return $this->model->hydrateRows($results);
    }

    /**
     * Get the relation instance for the given relation name.
     *
     * @param  string  $name
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     */
    public function getRelation($name)
    {
        // We want to run a relationship query without any constrains so that we will
        // not have to remove these where clauses manually which gets really hacky
        // and is error prone while we remove the developer's own where clauses.
        $relation = Relation::noConstraints(
            function () use ($name) {
                try {
                    return $this->getModel()->$name();
                } catch (BadMethodCallException $e) {
                    throw RelationNotFoundException::make($this->getModel(), $name);
                }
            }
        );

        $nested = $this->nestedRelations($name);

        // If there are nested relationships set on the query, we will put those onto
        // the query instances so that they can be handled after this relationship
        // is loaded. In this way they will all trickle down as they are loaded.
        if (count($nested) > 0) {
            $relation->getQuery()->with($nested);
        }

        return $relation;
    }

    /**
     * Find a model by its primary key.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Collection|static[]|static|null
     */
    public function find($id, $columns = ['*'])
    {
        if (is_array($id)) {
            return $this->findMany($id, $columns);
        }

        if (is_string($id)) {
            $id = new \Cassandra\Uuid($id);
        }

        $this->query->where($this->model->getQualifiedKeyName(), '=', $id);

        return $this->first($columns);
    }

    /**
     * Find multiple models by their primary keys.
     *
     * @param  array  $ids
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findMany($ids, $columns = ['*'])
    {
        if (empty($ids)) {
            return $this->model->newCollection();
        }

        foreach ($ids as &$id) {
            if (is_string($id)) {
                $id = new \Cassandra\Uuid($id);
            }
        }

        $this->query->whereIn($this->model->getQualifiedKeyName(), $ids);

        return $this->get($columns);
    }

}
