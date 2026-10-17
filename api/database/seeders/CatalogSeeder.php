<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Store;
use Illuminate\Database\Seeder;

/**
 * Starting catalog for Australia: the main supermarket chains and a category tree.
 * Safe to run more than once (php artisan db:seed --class=CatalogSeeder).
 */
class CatalogSeeder extends Seeder
{
    private const STORES = [
        'Woolworths' => ['WOOLWORTHS', 'WOOLIES', 'WOOLWORTHS METRO'],
        'Coles' => ['COLES', 'COLES LOCAL', 'COLES EXPRESS'],
        'Aldi' => ['ALDI', 'ALDI STORES'],
        'IGA' => ['IGA', 'SUPA IGA', 'IGA XPRESS'],
        'Drakes' => ['DRAKES', 'DRAKES SUPERMARKETS'],
        'Foodland' => ['FOODLAND'],
        'Costco' => ['COSTCO', 'COSTCO WHOLESALE'],
        'Harris Farm' => ['HARRIS FARM', 'HARRIS FARM MARKETS'],
    ];

    private const CATEGORIES = [
        'Dairy & Eggs' => ['Milk', 'Cheese', 'Yoghurt', 'Butter & Spreads', 'Cream', 'Eggs'],
        'Bakery' => ['Bread', 'Wraps & Rolls', 'Cakes & Pastries'],
        'Fruit & Vegetables' => ['Fruit', 'Vegetables', 'Salad & Herbs'],
        'Meat & Seafood' => ['Beef', 'Chicken', 'Pork', 'Lamb', 'Seafood', 'Deli & Smallgoods'],
        'Pantry' => ['Rice', 'Pasta & Noodles', 'Canned Food', 'Breakfast Cereal', 'Baking', 'Sauces & Condiments', 'Oils & Vinegars', 'Spreads', 'Snacks', 'Biscuits', 'Confectionery', 'Tea & Coffee'],
        'Drinks' => ['Soft Drinks', 'Juice', 'Water', 'Sports & Energy Drinks'],
        'Frozen' => ['Frozen Meals', 'Frozen Vegetables', 'Ice Cream'],
        'Household' => ['Cleaning', 'Laundry', 'Paper & Tissues'],
        'Health & Beauty' => ['Personal Care', 'Vitamins', 'Oral Care'],
        'Baby' => ['Nappies', 'Baby Food'],
        'Pet' => ['Pet Food'],
    ];

    public function run(): void
    {
        foreach (self::STORES as $name => $aliases) {
            Store::firstOrCreate(['name' => $name], ['aliases' => $aliases]);
        }

        foreach (self::CATEGORIES as $parentName => $children) {
            $parent = Category::firstOrCreate(['name' => $parentName]);

            foreach ($children as $child) {
                Category::firstOrCreate(['name' => $child], ['parent_id' => $parent->id]);
            }
        }
    }
}
