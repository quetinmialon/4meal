<?php

namespace App\Events;

use App\Models\CookbookMessage;

final class CookbookMessageCreated
{
    public function __construct(public readonly CookbookMessage $message) {}
}
