<?php

namespace Ahs12\Setanjo\Enums;

enum HistoryAction: string
{
    case SET = 'set';
    case FORGET = 'forget';
    case META = 'meta';
    case GROUP = 'group';
    case TOGGLE = 'toggle';
}
