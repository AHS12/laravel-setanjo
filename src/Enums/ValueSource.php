<?php

namespace Ahs12\Setanjo\Enums;

/**
 * Where an effective value was resolved from.
 */
enum ValueSource: string
{
    case SCOPE = 'scope';
    case GLOBAL = 'global';
    case DEFAULT = 'default';
}
