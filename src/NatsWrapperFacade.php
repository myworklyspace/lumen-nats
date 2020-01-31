<?php

namespace WebDevil\NatsWrapper;

use Illuminate\Support\Facades\Facade;

class NatsWrapperFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return PubSub::class;
    }
}