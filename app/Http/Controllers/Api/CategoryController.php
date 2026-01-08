<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    /**
     * Get all categories
     */
    public function index()
    {
        $categories = Category::orderBy('name')->get();
        return response()->json($categories);
    }

    /**
     * Create a new category
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:categories,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $category = Category::create([
            'name' => trim($request->name)
        ]);

        return response()->json($category, 201);
    }

    /**
     * Find or create category by name
     * This is useful for bulk operations
     */
    public function findOrCreate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'names' => 'required|array',
            'names.*' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $categories = [];
        foreach ($request->names as $name) {
            $categories[] = Category::firstOrCreate(
                ['name' => trim($name)]
            );
        }

        return response()->json($categories);
    }
}