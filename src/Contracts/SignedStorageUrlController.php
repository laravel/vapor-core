<?php

namespace Laravel\Vapor\Contracts;

use Illuminate\Http\Request;

interface SignedStorageUrlController
{
    /**
     * Create a new signed URL.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request);
}
