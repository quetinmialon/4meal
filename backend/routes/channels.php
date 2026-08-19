<?php

use App\Broadcasting\CookbookChannel;
use App\Broadcasting\UserChannel;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{userId}', UserChannel::class);
Broadcast::channel('cookbook.{cookbook}', CookbookChannel::class);
