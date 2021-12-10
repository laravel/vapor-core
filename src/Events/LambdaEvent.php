<?php

namespace Laravel\Vapor\Events;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;

class LambdaEvent implements ArrayAccess, Arrayable
{
    /**
     * The underlying event.
     *
     * @var array
     */
    protected $event;

    /**
     * Creates a new event instance.
     *
     * @param  array  $event
     * @return void
     */
    public function __construct($event)
    {
        $this->event = $event;
    }

    /**
     * Determine if an item exists at an offset.
     *
     * @param  string  $key
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($key)
    {
        return Arr::exists($this->event, $key);
    }

    /**
     * Get an item at a given offset.
     *
     * @param  string  $key
     * @return array|string|int
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($key)
    {
        return Arr::get($this->event, $key);
    }

    /**
     * Set the item at a given offset.
     *
     * @param  string  $key
     * @param  array|string|int  $value
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($key, $value)
    {
        Arr::set($this->event, $key, $value);
    }

    /**
     * Unset the item at a given offset.
     *
     * @param  string  $key
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($key)
    {
        Arr::forget($this->event, $key);
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->event;
    }
}
