<?php

namespace Ahs12\Setanjo\Http\Controllers\Dashboard;

use Ahs12\Setanjo\Enums\HistoryAction;
use Ahs12\Setanjo\Enums\SettingType;
use Ahs12\Setanjo\Exceptions\DashboardException;
use Ahs12\Setanjo\Listeners\RecordSettingHistory;
use Ahs12\Setanjo\Models\SettingHistory;
use Ahs12\Setanjo\Support\ScopeKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'key' => 'sometimes|nullable|string',
            'action' => ['sometimes', 'nullable', Rule::enum(HistoryAction::class)],
            'scope_type' => 'sometimes|nullable|string',
            'scope_id' => 'sometimes|nullable',
            'per_page' => 'sometimes|integer|min:1|max:200',
        ]);

        if (! config('setanjo.history.enabled', false)) {
            return response()->json([
                'enabled' => false,
                'message' => __('setanjo::dashboard.history_disabled'),
                'data' => [],
            ]);
        }

        $scopeClass = $request->filled('scope_type') ? $this->scopes->type($request->input('scope_type'))['class'] : null;

        $page = SettingHistory::query()
            ->when($request->filled('key'), fn ($query) => $query->where('setting_key', $request->input('key')))
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->input('action')))
            ->when($scopeClass !== null, fn ($query) => $query->where('scope_type', $scopeClass))
            ->when($request->filled('scope_id'), fn ($query) => ScopeKey::fits(new SettingHistory, 'scope_id', $request->input('scope_id'))
                ? $query->where('scope_id', $request->input('scope_id'))
                : $query->whereRaw('1 = 0'))
            ->orderByDesc('id')
            ->paginate((int) $request->input('per_page', 50));

        return response()->json([
            'enabled' => true,
            'data' => collect($page->items())->map(fn (SettingHistory $entry) => [
                'id' => $entry->id,
                'key' => $entry->setting_key,
                'action' => $entry->action,
                'scope' => $this->scopes->reference($entry->scope_type, $entry->scope_id),
                'causer' => $this->scopes->reference($entry->causer_type, $entry->causer_id),
                'old_value' => $entry->old_value,
                'new_value' => $entry->new_value,
                'old_meta' => $entry->old_meta,
                'new_meta' => $entry->new_meta,
                'kind' => self::kind($entry),
                'revertable' => $this->blocker($entry) === null,
                'created_at' => $entry->created_at->toIso8601String(),
            ])->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * Undo one change: write back the value, meta or flag state it replaced, for the same scope.
     * The revert is itself recorded as a new change.
     */
    public function revert(int $history): JsonResponse
    {
        if (! config('setanjo.history.enabled', false)) {
            throw DashboardException::invalid(__('setanjo::dashboard.history_disabled'));
        }

        $entry = SettingHistory::query()->find($history)
            ?? throw DashboardException::notFound(__('setanjo::dashboard.history_not_found', ['id' => $history]));

        if (($blocker = $this->blocker($entry)) !== null) {
            throw DashboardException::invalid($blocker);
        }

        $key = $entry->setting_key;
        $scope = $entry->scope_type === null ? null : $this->entryScope($entry);
        $manager = $scope === null ? $this->setanjo : $this->setanjo->for($scope);

        if ($entry->action === HistoryAction::RULES->value) {
            $this->attempt(fn () => $this->setanjo->feature($key)->updateRules($entry->old_meta ?? []));
        } elseif ($entry->action === HistoryAction::TOGGLE->value) {
            $this->attempt(fn () => $this->setanjo->feature($key)->for($scope)->toggle(
                $entry->old_value === RecordSettingHistory::INHERIT ? null : $entry->old_value === 'true',
            ));
        } elseif ($entry->action === HistoryAction::META->value) {
            $this->attempt(fn () => $this->setanjo->replaceMeta($key, $entry->old_meta));
        } else {
            if ($this->catalog->setting($key)['readonly'] ?? false) {
                throw DashboardException::forbidden(__('setanjo::dashboard.locked', ['key' => $key]));
            }

            $entry->old_value === null
                ? $manager->forget($key)
                : $this->attempt(fn () => $manager->set($key, $this->decode($key, $entry->old_value)));
        }

        return response()->json([
            'data' => [
                'key' => $key,
                'kind' => self::kind($entry),
                'scope' => $scope === null ? null : $this->scopes->present($scope),
            ],
        ]);
    }

    /**
     * Why an entry cannot be reverted, or null when it can.
     */
    protected function blocker(SettingHistory $entry): ?string
    {
        if (! in_array($entry->action, [HistoryAction::SET->value, HistoryAction::FORGET->value, HistoryAction::META->value, HistoryAction::TOGGLE->value, HistoryAction::RULES->value], true)) {
            return __('setanjo::dashboard.revert_unsupported');
        }

        if ($entry->old_value === RecordSettingHistory::MASK || $entry->new_value === RecordSettingHistory::MASK) {
            return __('setanjo::dashboard.revert_masked');
        }

        if ($entry->action === HistoryAction::TOGGLE->value && $entry->old_value === null) {
            return __('setanjo::dashboard.revert_unknown_state');
        }

        return null;
    }

    protected static function kind(SettingHistory $entry): string
    {
        return HistoryAction::tryFrom($entry->action)?->isFeature() ? 'feature' : 'setting';
    }

    /**
     * The record the entry was written for. A deleted or unknown record cannot be written to.
     */
    protected function entryScope(SettingHistory $entry): Model
    {
        $alias = $this->scopes->aliasOf($entry->scope_type);

        if ($alias === null) {
            throw DashboardException::invalid(__('setanjo::dashboard.unknown_scope', ['type' => $entry->scope_type]));
        }

        return $this->scopes->find($alias, (string) $entry->scope_id);
    }

    /**
     * History stores strings as they are and everything else as JSON.
     */
    protected function decode(string $key, string $value): mixed
    {
        if (($this->catalog->setting($key)['type'] ?? SettingType::STRING->value) === SettingType::STRING->value) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}
