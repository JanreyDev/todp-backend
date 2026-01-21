<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * Get all categories with dataset counts
     */
    public function index()
    {
        $categories = Category::withCount([
            'contributes' => function ($query) {
                $query->where('status', 'approved');
            }
        ])
        ->orderBy('name')
        ->get()
        ->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => Str::slug($category->name),
                'datasets_count' => $category->contributes_count ?? 0,
                'description' => $category->description ?? $this->getCategoryDescription($category->name),
                'icon' => $category->icon ?? $this->getCategoryIcon($category->name),
            ];
        });

        return response()->json($categories);
    }

    /**
     * Store a new category
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'icon' => 'required|string|max:50',
            'description' => 'nullable|string|max:500',
        ]);

        $category = Category::create($validated);

        return response()->json([
            'message' => 'Category created successfully',
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => Str::slug($category->name),
                'icon' => $category->icon,
                'description' => $category->description,
                'datasets_count' => 0,
            ],
        ], 201);
    }

    /**
     * Find existing category or create new one
     */
    public function findOrCreate(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $category = Category::firstOrCreate(
            ['name' => $validated['name']]
        );

        return response()->json([
            'category' => $category,
            'created' => $category->wasRecentlyCreated,
        ]);
    }

    /**
     * Get category description based on name
     * Fallback for old categories without description
     */
    private function getCategoryDescription(string $name): string
    {
        $descriptions = [
            'Education' => 'Schools, enrollment, and educational facilities data',
            'Population & Demographics' => 'Census, age distribution, and socioeconomic indicators',
            'Health & Hospitals' => 'Healthcare facilities, health workers, and health statistics',
            'Infrastructure' => 'Roads, bridges, public buildings, and utilities',
            'Agriculture & Fisheries' => 'Farmland, crop production, and agricultural data',
            'Environment' => 'Forest cover, watersheds, and environmental monitoring',
            'Business & Economy' => 'Business registration, investment, and economic indicators',
            'Governance & Budget' => 'Budget, expenditures, bids, and procurement data',
            'Public Safety' => 'Police, fire stations, and emergency response data',
            'Transportation' => 'Public transport, traffic, and mobility data',
            'Tourism' => 'Tourist attractions, accommodations, and visitor statistics',
            'Social Services' => 'Welfare programs, community services, and assistance data',
            'Technology & Innovation' => 'Digital infrastructure, IT projects, and innovation initiatives',
            'Energy' => 'Power generation, distribution, and energy consumption data',
            'Water Resources' => 'Water supply, distribution, and quality monitoring',
            'Finance & Revenue' => 'Tax collection, revenue sources, and financial reports',
            'Housing & Urban Development' => 'Housing projects, urban planning, and land use data',
            'Public Records' => 'Official documents, permits, and administrative records',
            'Climate & Weather' => 'Weather patterns, climate data, and meteorological information',
            'Culture & Heritage' => 'Cultural sites, heritage preservation, and arts programs',
            'Geography & Maps' => 'Geographical information, topography, and mapping data',
            'Open Data' => 'General open data resources and datasets',
        ];

        return $descriptions[$name] ?? 'Data related to ' . strtolower($name);
    }

    /**
     * Get icon name for category based on name
     * Fallback for old categories without icon
     */
    private function getCategoryIcon(string $name): string
    {
        $icons = [
            'Education' => 'GraduationCap',
            'Population & Demographics' => 'Users',
            'Health & Hospitals' => 'Heart',
            'Infrastructure' => 'Building2',
            'Agriculture & Fisheries' => 'Wheat',
            'Environment' => 'TreePine',
            'Business & Economy' => 'TrendingUp',
            'Governance & Budget' => 'Landmark',
            'Public Safety' => 'Shield',
            'Transportation' => 'AlertTriangle',
            'Tourism' => 'HeartHandshake',
            'Social Services' => 'HeartHandshake',
            'Technology & Innovation' => 'Wifi',
            'Energy' => 'Droplets',
            'Water Resources' => 'Droplets',
            'Finance & Revenue' => 'Banknote',
            'Housing & Urban Development' => 'Home',
            'Public Records' => 'Eye',
            'Climate & Weather' => 'Cloud',
            'Culture & Heritage' => 'BookOpen',
            'Geography & Maps' => 'MapPin',
            'Open Data' => 'Database',
        ];

        return $icons[$name] ?? 'Database';
    }
}