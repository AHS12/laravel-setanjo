<?php

namespace Ahs12\Setanjo\Http\Controllers\Dashboard;

use Ahs12\Setanjo\Enums\HistoryAction;
use Ahs12\Setanjo\Models\SettingHistory;
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
            ->when($request->filled('scope_id'), fn ($query) => $query->where('scope_id', $request->input('scope_id')))
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
                'created_at' => $entry->created_at->toIso8601String(),
            ])->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
