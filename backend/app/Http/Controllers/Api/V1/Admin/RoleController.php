<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => [
            'roles' => Role::query()->with('permissions:id,slug')->withCount('users')->orderBy('id')->get()->map(fn (Role $r) => [
                'id' => $r->id, 'slug' => $r->slug, 'name' => $r->name, 'description' => $r->description, 'isAdmin' => $r->is_admin,
                'permissions' => $r->permissions->pluck('slug'), 'usersCount' => $r->users_count,
            ]),
            'permissions' => Permission::query()->orderBy('group')->orderBy('slug')->get(['id', 'slug', 'name', 'group']),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $role = Role::query()->create(collect($data)->except('permissions')->all());
        $role->permissions()->sync(Permission::query()->whereIn('slug', $data['permissions'] ?? [])->pluck('id'));
        $this->audit->log($request->user(), 'role.created', $role, null, $data);

        return response()->json(['data' => ['id' => $role->id]], 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        abort_if($role->slug === 'super-admin', 422, 'The super-admin role always has every permission.');
        $data = $this->validated($request, $role);
        $before = $role->permissions()->pluck('slug')->all();
        $role->update(collect($data)->except('permissions')->all());
        $role->permissions()->sync(Permission::query()->whereIn('slug', $data['permissions'] ?? [])->pluck('id'));
        $this->audit->log($request->user(), 'role.updated', $role, null, ['before' => $before, 'after' => $data['permissions'] ?? []]);

        return response()->json(['message' => 'Role updated.']);
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        abort_if(in_array($role->slug, ['super-admin', 'customer'], true), 422, 'Built-in roles cannot be deleted.');
        abort_if($role->users()->exists(), 422, 'Remove this role from all users first.');
        $role->delete();
        $this->audit->log($request->user(), 'role.deleted', $role);

        return response()->json(['message' => 'Role deleted.']);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'slug' => ['required', 'alpha_dash', 'max:64', Rule::unique('roles')->ignore($role?->id)],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_admin' => ['boolean'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,slug'],
        ]);
    }
}
