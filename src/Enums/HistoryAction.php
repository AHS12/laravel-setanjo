<?php

namespace Ahs12\Setanjo\Enums;

enum HistoryAction: string
{
    case SET = 'set';
    case FORGET = 'forget';
    case META = 'meta';
    case GROUP = 'group';
    case TOGGLE = 'toggle';
    case RULES = 'rules';

    /**
     * Whether the entry is about a feature flag (else a setting).
     */
    public function isFeature(): bool
    {
        return $this === self::TOGGLE || $this === self::RULES;
    }
}
