<?php

namespace App\Http\Controllers;

use App\Http\Resources\ShoppingListResource;
use App\Models\ShoppingList;
use App\Pricing\ShoppingListComparison;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ShoppingListController extends Controller
{
    public function index(Request $request)
    {
        return ShoppingListResource::collection(
            ShoppingList::whereBelongsTo($request->user())
                ->withCount(['items', 'items as unchecked_count' => fn ($query) => $query->where('is_checked', false)])
                ->latest('updated_at')
                ->latest('id')
                ->get()
        );
    }

    public function store(Request $request): ShoppingListResource
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);

        return new ShoppingListResource(ShoppingList::create([...$data, 'user_id' => $request->user()->id])->loadCount('items'));
    }

    public function show(ShoppingList $shoppingList): ShoppingListResource
    {
        $this->authorize('view', $shoppingList);

        return new ShoppingListResource($shoppingList->load('items.product.brand'));
    }

    public function update(Request $request, ShoppingList $shoppingList): ShoppingListResource
    {
        $this->authorize('update', $shoppingList);

        $shoppingList->update($request->validate(['name' => ['required', 'string', 'max:100']]));

        return new ShoppingListResource($shoppingList);
    }

    public function destroy(ShoppingList $shoppingList): Response
    {
        $this->authorize('delete', $shoppingList);

        $shoppingList->delete();

        return response()->noContent();
    }

    /**
     * What the list costs at each store, the cheapest store and a split-shop plan.
     */
    public function comparison(Request $request, ShoppingList $shoppingList): JsonResponse
    {
        $this->authorize('view', $shoppingList);

        return response()->json(['data' => (new ShoppingListComparison($shoppingList, $request->user()))->toArray()]);
    }
}
