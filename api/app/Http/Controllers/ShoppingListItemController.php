<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShoppingList\SaveShoppingListItemRequest;
use App\Http\Resources\ShoppingListItemResource;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use Illuminate\Http\Response;

class ShoppingListItemController extends Controller
{
    /**
     * Add a product or note. Adding a product that's already on the list adds to its quantity.
     */
    public function store(SaveShoppingListItemRequest $request, ShoppingList $shoppingList): ShoppingListItemResource
    {
        $this->authorize('update', $shoppingList);

        $existing = $request->filled('product_id')
            ? $shoppingList->items()->where('product_id', $request->integer('product_id'))->first()
            : null;

        if ($existing) {
            $existing->update(['quantity' => $existing->quantity + ($request->validated('quantity') ?? 1), 'is_checked' => false]);
            $item = $existing;
        } else {
            $item = $shoppingList->items()->create([
                'quantity' => 1,
                'is_checked' => false,
                ...$request->validated(),
                'position' => ($shoppingList->items()->max('position') ?? 0) + 1,
            ]);
        }

        $shoppingList->touch();

        return new ShoppingListItemResource($item->load('product.brand'));
    }

    /**
     * Tick off, change the quantity, or link a note to a product.
     */
    public function update(SaveShoppingListItemRequest $request, ShoppingList $shoppingList, ShoppingListItem $item): ShoppingListItemResource
    {
        $this->authorizeItem($shoppingList, $item);

        $item->update($request->validated());
        $shoppingList->touch();

        return new ShoppingListItemResource($item->load('product.brand'));
    }

    public function destroy(ShoppingList $shoppingList, ShoppingListItem $item): Response
    {
        $this->authorizeItem($shoppingList, $item);

        $item->delete();
        $shoppingList->touch();

        return response()->noContent();
    }

    private function authorizeItem(ShoppingList $shoppingList, ShoppingListItem $item): void
    {
        $this->authorize('update', $shoppingList);
        abort_unless($item->shopping_list_id === $shoppingList->id, 404);
    }
}
