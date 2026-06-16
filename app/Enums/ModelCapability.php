<?php

namespace App\Enums;

enum ModelCapability: string
{
    case Analyze = 'analyze';
    case Generate = 'generate';
}
