<?php

namespace Ahs12\Setanjo\Http\Controllers\Dashboard;

use Ahs12\Setanjo\Dashboard\Catalog;
use Ahs12\Setanjo\Models\SettingGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroupsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->setanjo->groups()->all()->map(fn (SettingGroup $group) => $this->present($group))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $attributes = $request->validate($this->rules(required: true));

        $group = $this->attempt(fn () => $this->setanjo->groups()->create($attributes));

        return response()->json(['data' => $this->present($group)], 201);
    }

    public function update(Request $request, int $group): JsonResponse
    {
        $attributes = $request->validate($this->rules(required: false));

        $model = $this->attempt(fn () => $this->setanjo->groups()->update($group, $attributes));

        return response()->json(['data' => $this->present($model)]);
    }

    public function destroy(int $group): JsonResponse
    {
        $this->setanjo->groups()->delete($group);

        return response()->json(null, 204);
    }

    public function order(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'present|array',
            'ids.*' => 'integer',
        ]);

        $this->setanjo->groups()->reorder($request->input('ids'));

        return $this->index();
    }

    /**
     * @return array<string, string>
     */
    protected function rules(bool $required): array
    {
        $name = $required ? 'required' : 'sometimes|required';

        return [
            'name' => $name.'|string|max:255',
            'slug' => 'sometimes|nullable|string|max:255',
            'description' => 'sometimes|nullable|string',
            'icon' => 'sometimes|nullable|string|max:255',
            'meta' => 'sometimes|nullable|array',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function present(SettingGroup $group): array
    {
        return [
            'id' => $group->getKey(),
            'name' => $group->name,
            'slug' => $group->slug,
            'description' => $group->description,
            'icon' => $group->icon,
            'sort_order' => (int) $group->sort_order,
            'meta' => is_array($group->meta) ? Catalog::jsonObject($group->meta) : null,
        ];
    }
}
