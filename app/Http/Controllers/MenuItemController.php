<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderMenuItemsRequest;
use App\Http\Requests\StoreMenuItemRequest;
use App\Http\Requests\UpdateMenuItemRequest;
use App\Models\MenuItem;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuItemController extends Controller
{
    /**
     * GET /api/menu
     *
     * Liste plate complète (usage admin uniquement).
     */
    public function index(): JsonResponse
    {
        $items = MenuItem::with('roles:id,name')
            ->orderBy('ordre')
            ->get();

        return response()->json($items);
    }

    /**
     * GET /api/menu/tree
     *
     * Arbre hiérarchique filtré selon les rôles de l'utilisateur connecté.
     * Les admins voient tout.
     */
    public function tree(Request $request): JsonResponse
    {
        $user      = $request->user();
        $roleNames = $user ? $user->roles->pluck('name')->all() : [];
        $isAdmin   = in_array('admin', $roleNames, true);

        $roots = MenuItem::with([
            'children' => function ($q) use ($roleNames, $isAdmin): void {
                $q->visible()->orderBy('ordre');

                if (! $isAdmin && ! empty($roleNames)) {
                    $q->whereHas(
                        'roles',
                        fn ($rq) => $rq->whereIn('name', $roleNames)
                    );
                }
            },
        ])
        ->visible()
        ->roots()
        ->orderBy('ordre')
        ->when(
            ! $isAdmin && ! empty($roleNames),
            fn ($q) => $q->whereHas(
                'roles',
                fn ($rq) => $rq->whereIn('name', $roleNames)
            )
        )
        ->get();

        return response()->json($roots);
    }

    /**
     * POST /api/menu
     */
    public function store(StoreMenuItemRequest $request): JsonResponse
    {
        $maxOrdre = MenuItem::where('parent_id', $request->parent_id)
            ->max('ordre') ?? -1;

        $item = MenuItem::create([
            ...$request->only([
                'parent_id', 'label_fr', 'label_ar', 'label_en',
                'icon', 'route', 'is_visible', 'module',
            ]),
            'ordre' => $maxOrdre + 1,
        ]);

        if ($request->filled('roles')) {
            $item->roles()->sync(
                Role::whereIn('name', $request->roles)->pluck('id')
            );
        }

        return response()->json($item->load('roles:id,name'), 201);
    }

    /**
     * GET /api/menu/{menuItem}
     */
    public function show(MenuItem $menuItem): JsonResponse
    {
        return response()->json($menuItem->load(['children', 'roles:id,name']));
    }

    /**
     * PUT /api/menu/{menuItem}
     */
    public function update(UpdateMenuItemRequest $request, MenuItem $menuItem): JsonResponse
    {
        $menuItem->update($request->only([
            'parent_id', 'label_fr', 'label_ar', 'label_en',
            'icon', 'route', 'ordre', 'is_visible', 'module',
        ]));

        if ($request->has('roles')) {
            $menuItem->roles()->sync(
                Role::whereIn('name', $request->roles)->pluck('id')
            );
        }

        return response()->json($menuItem->fresh()->load('roles:id,name'));
    }

    /**
     * DELETE /api/menu/{menuItem}
     *
     * Supprime l'élément et ses enfants en cascade (FK onDelete cascade).
     */
    public function destroy(MenuItem $menuItem): JsonResponse
    {
        $menuItem->delete();

        return response()->json(['message' => 'Élément de menu supprimé.']);
    }

    /**
     * PATCH /api/menu/{menuItem}/toggle-visibility
     */
    public function toggleVisibility(MenuItem $menuItem): JsonResponse
    {
        $menuItem->update(['is_visible' => ! $menuItem->is_visible]);

        return response()->json($menuItem->fresh());
    }

    /**
     * POST /api/menu/reorder
     *
     * Body: { "items": [{ "id": 1, "ordre": 0 }, ...] }
     */
    public function reorder(ReorderMenuItemsRequest $request): JsonResponse
    {
        foreach ($request->validated('items') as $item) {
            MenuItem::where('id', $item['id'])->update(['ordre' => $item['ordre']]);
        }

        return response()->json(['message' => 'Ordre mis à jour.']);
    }
}
