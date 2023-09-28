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
     * Apply the callback if the environment is Vapor.
     */
    public static function whenActive($whenActive, $whenInactive = null)
    {
        if (static::active()) {
            return value($whenActive);
        }

        return value($whenInactive);
    }

    /**
     * Apply the callback if the environment is not Vapor.
     */
    public static function whenInactive($whenInactive, $whenActive = null)
    {
        return static::whenActive($whenActive, $whenInactive);
    }
}
