<?php

namespace Hey\Lacassa\Eloquent;

use Exception;

trait SoftDeletes
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    /**
     * Boot the soft deleting trait for a model.
     *
     * @return void
     *
     * @throws \Exception
     */
    public static function bootSoftDeletes()
    {
        // throw new Exception('Not implemented');
        static::addGlobalScope(new SoftDeletingScope);
    }

    /**
     * @inheritdoc
     */
    public function getQualifiedDeletedAtColumn()
    {
        return $this->getDeletedAtColumn();
    }
    
    /**
     * @inheritdoc
     */
    public function getQualifiedDeletedColumn()
    {
        return $this->getDeletedColumn();
    }

    /**
     * Determine if the model instance has been soft-deleted.
     *
     * @return bool
     */
    public function trashed()
    {
        return $this->{$this->getDeletedAtColumn()} != '';
    }

    /**
     * Get the name of the "deleted at" column.
     *
     * @return string
     */
    public function getDeletedColumn()
    {
        return defined('static::DELETED') ? static::DELETED : 'deleted';
    }

}
