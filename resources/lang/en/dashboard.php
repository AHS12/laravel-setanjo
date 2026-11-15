<?php

return [
    'forbidden' => 'You are not allowed to open the settings dashboard.',
    'readonly' => 'The settings dashboard is read-only for you.',
    'unprotected' => 'The settings dashboard needs a signed-in user here. Add an auth middleware or Authorize to setanjo.dashboard.middleware.',
    'locked' => 'Setting [:key] is locked and cannot be changed here.',
    'unknown_scope' => 'Scope type [:type] is not configured for the dashboard.',
    'scope_not_found' => 'No [:type] record with id [:id].',
    'scope_hidden' => '[:type] #[:id] exists but a global scope hides it from the dashboard. Set without_scopes for this type in setanjo.scopes.',
    'history_disabled' => 'History is not enabled. Set setanjo.history.enabled to true.',
    'invalid_import' => 'The file is not a valid setanjo export.',
];
