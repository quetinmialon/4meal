<?php

namespace App\Enums;

enum Diet: string
{
    case Omnivore = 'omnivore';
    case Vegetarian = 'vegetarian';
    case Vegan = 'vegan';
    case Pescatarian = 'pescatarian';
    case Flexitarian = 'flexitarian';
    case Halal = 'halal';
    case Kosher = 'kosher';
}
