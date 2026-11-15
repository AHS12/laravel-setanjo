<?php

return [
    'forbidden' => 'You are not allowed to access the settings.',
    'readonly' => 'You can view the settings but not change them.',
    'unprotected' => 'The settings dashboard needs a signed-in user here. Add an auth middleware or Authorize to setanjo.dashboard.middleware.',
    'locked' => 'Setting [:key] is locked and cannot be changed here.',
    'unknown_scope' => 'Scope type [:type] is not configured for the dashboard.',
    'scope_not_found' => 'No [:type] record with id [:id].',
    'scope_hidden' => '[:type] #[:id] exists but a global scope hides it from the dashboard. Set without_scopes for this type in setanjo.scopes.',
    'history_disabled' => 'History is not enabled. Set setanjo.history.enabled to true.',
    'history_not_found' => 'No history entry with id [:id].',
    'revert_unsupported' => 'This kind of change cannot be reverted from the dashboard.',
    'revert_masked' => 'The old value of a sensitive setting is not stored, so it cannot be restored.',
    'revert_unknown_state' => 'This change was recorded before the previous flag state was stored, so it cannot be reverted.',
    'unknown_key' => 'There is no setting or feature flag [:key].',
    'invalid_import' => 'The file is not a valid setanjo export.',
];
