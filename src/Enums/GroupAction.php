<?php

namespace Ahs12\Setanjo\Enums;

enum GroupAction: string
{
    case CREATED = 'created';
    case UPDATED = 'updated';
    case DELETED = 'deleted';
    case REORDERED = 'reordered';
    case ASSIGNED = 'assigned';
}
