<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TagController extends Controller
{
    /**
     * Get all tags
     */
    public function index()
    {
        $tags = Tag::orderBy('name')->get();
        return response()->json($tags);
    }

    /**
     * Create a new tag
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:tags,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $tag = Tag::create([
            'name' => trim($request->name)
        ]);

        return response()->json($tag, 201);
    }

    /**
     * Find or create tag by name
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

        $tags = [];
        foreach ($request->names as $name) {
            $tags[] = Tag::firstOrCreate(
                ['name' => trim($name)]
            );
        }

        return response()->json($tags);
    }
}