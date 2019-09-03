<?php

namespace Laravel\Vapor;

/*
The MIT License (MIT)

Copyright (c) 2018 Matthieu Napoli

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
/*

/**
 * @author Taylor Otwell <taylor@laravel.com>
 * @author Matthieu Napoli <matthieu@mnapoli.fr>
 */
class Arr
{
    /**
     * Set a multi-part body array value in the given array.
     *
     * @param  array  $array
     * @param  string  $key
     * @param  mixed  $value
     * @return array
     */
    public static function setMultipartArrayValue(array $array, string $name, $value)
    {
        $segments = explode('[', $name);

        $pointer = &$array;

        foreach ($segments as $key => $segment) {
            // If this is our first time through the loop we will just grab the initial
            // key's part of the array. After this we will start digging deeper into
            // the array as needed until we get to the correct depth in the array.
            if ($key === 0) {
                $pointer = &$pointer[$segment];

                continue;
            }

            // If this segment is malformed, we will just use the key as-is since there
            // is nothign we can do with it from here. We will return the array back
            // to the caller with the value set. We cannot continue looping on it.
            if (static::malformedMultipartSegment($segment)) {
                $array[$name] = $value;

                return $array;
            }

            // If the segment is empty after trimming off the closing bracket, it means
            // we are at the end of the segment and are ready to set the value so we
            // can grab a pointer to the array location and set it after the loop.
            if (empty($segment = substr($segment, 0, -1))) {
                $pointer = &$pointer[];
            } else {
                $pointer = &$pointer[$segment];
            }
        }

        $pointer = $value;

        return $array;
    }

    /**
     * Determine if the given multi-part value segment is malformed.
     *
     * This can occur when there are two [[ or no closing bracket.
     *
     * @param  string  $segment
     * @return bool
     */
    protected static function malformedMultipartSegment($segment)
    {
        return $segment === '' || substr($segment, -1) !== ']';
    }
}
