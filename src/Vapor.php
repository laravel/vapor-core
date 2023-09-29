<?php

namespace Laravel\Vapor;

class Vapor
{
    /**
     * Determine whether the environment is Vapor.
     */
    public static function active(): bool
    {
        return env('VAPOR_SSM_PATH') !== null;
    }

    /**
     * Determine whether the environment is not Vapor.
     */
    public static function inactive(): bool
    {
        return ! static::active();
    }

    /**
     * Execute the callback if the environment is Vapor.
     *
     * @param  mixed  $whenActive
     * @param  mixed  $whenInactive
     * @return mixed
     */
    public static function whenActive($whenActive, $whenInactive = null)
    {
        if (static::active()) {
            return value($whenActive);
        }

        return value($whenInactive);
    }

    /**
     * Execute the callback if the environment is not Vapor.
     *
     * @param  mixed  $whenInactive
     * @param  mixed  $whenActive
     * @return mixed
     */
    public static function whenInactive($whenInactive, $whenActive = null)
    {
        return static::whenActive($whenActive, $whenInactive);
    }
}
