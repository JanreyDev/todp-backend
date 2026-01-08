<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Contribute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ContributeController extends Controller
{
    /**
     * Store a new contribution (authenticated users only)
     * Status is automatically set to 'pending'
     */
    public function store(Request $request){
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'organization' => 'required|string|max:255',
            'request_type' => 'required|string|max:255',
            'message' => 'required|string',
            'file' => 'nullable|file|mimes:xlsx,xls,csv|max:10240', // 10MB max
        ]);

        if($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only(['title', 'organization', 'request_type', 'message']);

        // Handle file upload if present
        if($request->hasFile('file')){
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $data['file_path'] = $file->storeAs('uploads', $fileName, 'public');
        }

        // Set status to pending by default
        $data['status'] = 'pending';

        // Create contribution associated with authenticated user
        $contribute = auth('api')->user()->contributions()->create($data);

        // Load user relationship for response
        $contribute->load('user');

        return response()->json([
            'message' => 'Contribution submitted successfully and is pending review',
            'contribute' => $contribute
        ], 201);
    }

    /**
     * Get all contributions (Admin only)
     * Returns all contributions regardless of status
     */
    public function index(){ 
        $contributes = Contribute::with('user')
            ->latest()
            ->get()
            ->map(function($contribute) {
                return [
                    'id' => $contribute->id,
                    'title' => $contribute->title,
                    'organization' => $contribute->organization,
                    'request_type' => $contribute->request_type,
                    'message' => $contribute->message,
                    'file_path' => $contribute->file_path,
                    'status' => $contribute->status,
                    'created_at' => $contribute->created_at,
                    'updated_at' => $contribute->updated_at,
                    'user' => [
                        'id' => $contribute->user->id,
                        'name' => $contribute->user->name,
                        'email' => $contribute->user->email,
                    ]
                ];
            });
            
        return response()->json($contributes);
    }

    /**
     * Get single contribution details
     */
    public function show($id){
        $contribute = Contribute::with('user', 'categories', 'tags')->findOrFail($id);
        return response()->json($contribute);
    }

    /**
     * Update contribution (Admin only)
     * Can update status, categories, and tags
     */
    public function update(Request $request, $id){
        $contribute = Contribute::findOrFail($id);

        $validator = Validator::make($request->all(),[
            'status' => 'in:pending,approved,rejected',
            'categories' => 'array',
            'categories.*' => 'exists:categories,id',
            'tags' => 'array',
            'tags.*' => 'exists:tags,id'
        ]);

        if($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update status if provided
        if($request->has('status')) {
            $contribute->status = $request->status;
            $contribute->save();
        }

        // Sync categories if provided
        if($request->has('categories')) {
            $contribute->categories()->sync($request->categories);
        }

        // Sync tags if provided
        if($request->has('tags')) {
            $contribute->tags()->sync($request->tags);
        }

        $contribute->load('user', 'categories', 'tags');

        return response()->json([
            'message' => 'Contribution updated successfully',
            'contribute' => $contribute
        ]);
    }

    /**
     * Get public leaderboard (no auth required)
     * Shows top contributors with their stats
     */
    public function publicLeaderboard()
    {
        // Get top contributors with count and last contribution
        $leaders = Contribute::select('user_id')
            ->selectRaw('COUNT(*) as total_contributions')
            ->selectRaw('MAX(created_at) as last_contribution_date')
            ->where('status', 'approved') // Only count approved contributions
            ->groupBy('user_id')
            ->orderByDesc('total_contributions')
            ->limit(10) // Top 10 contributors
            ->get();

        // Attach user info and last contribution title
        $result = $leaders->map(function($item) {
            $user = \App\Models\User::find($item->user_id);
            $lastContrib = Contribute::where('user_id', $item->user_id)
                            ->where('status', 'approved')
                            ->latest()
                            ->first();

            return [
                'name' => $user->name ?? 'Unknown',
                'organization' => $lastContrib->organization ?? '-',
                'total_contributions' => $item->total_contributions,
                'last_title' => $lastContrib->title ?? null,
                'last_date' => $lastContrib->created_at ?? null,
            ];
        });

        return response()->json($result);
    }

    /**
     * Get approved contributions (public)
     * Returns only approved contributions for public viewing
     */
    public function approved()
    {
        $contributes = Contribute::with('user', 'categories', 'tags')
            ->where('status', 'approved')
            ->latest()
            ->paginate(15);
            
        return response()->json($contributes);
    }

    /**
     * Get user's own contributions
     * Returns contributions created by the authenticated user
     */
    public function myContributions()
    {
        $contributes = auth('api')->user()
            ->contributions()
            ->with('categories', 'tags')
            ->latest()
            ->get();
            
        return response()->json($contributes);
    }
}