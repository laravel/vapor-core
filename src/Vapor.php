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
    public static function whenActive(mixed $whenActive, mixed $whenInactive = null): mixed
    {
        if (static::active()) {
            return value($whenActive);
        }

        return value($whenInactive);
    }

    /**
     * Apply the callback if the environment is not Vapor.
     */
    public static function whenInactive(mixed $whenInactive, mixed $whenActive = null): mixed
    {
        return static::whenActive($whenActive, $whenInactive);
    }
}
