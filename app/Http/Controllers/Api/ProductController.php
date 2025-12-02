<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display the specified product with available stock
     */
    public function show($id)
    {
        $product = Product::findOrFail($id);
        
        return response()->json([
            'data' => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'total_stock' => $product->total_stock,
                'available_stock' => $product->available_stock,
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
            ]
        ]);
    }
}