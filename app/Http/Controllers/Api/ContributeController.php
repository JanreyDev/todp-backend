<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Contribute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ContributeController extends Controller
{
    /**
     * Store a new contribution (authenticated users only)
     * Status is automatically set to 'pending'
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'organization' => 'required|string|max:255',
            'request_type' => 'required|string|max:255',
            'message' => 'required|string',
            'file' => 'nullable|file|mimes:xlsx,xls,csv|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only(['title', 'organization', 'request_type', 'message']);

        // Handle file upload if present
        if ($request->hasFile('file')) {
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

    public function index()
    {
        $contributes = Contribute::with(['user', 'categories', 'tags'])
            ->latest()
            ->get()
            ->map(function ($contribute) {
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
                    ],
                    'categories' => $contribute->categories->map(function ($cat) {
                        return [
                            'id' => $cat->id,
                            'name' => $cat->name,
                        ];
                    }),
                    'tags' => $contribute->tags->map(function ($tag) {
                        return [
                            'id' => $tag->id,
                            'name' => $tag->name,
                        ];
                    }),
                ];
            });

        return response()->json($contributes);
    }

    public function show($id)
    {
        $contribute = Contribute::with('user', 'categories', 'tags')->findOrFail($id);
        return response()->json($contribute);
    }

    public function update(Request $request, $id)
    {
        try {
            $contribute = Contribute::findOrFail($id);

            // More flexible validation
            $validator = Validator::make($request->all(), [
                'status' => 'nullable|in:pending,approved,rejected',
                'categories' => 'nullable|array',
                'tags' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                \Log::error('Validation failed', ['errors' => $validator->errors()]);
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update status if provided
            if ($request->has('status')) {
                $contribute->status = $request->status;
                $contribute->save();
            }

            // Sync categories (handle both IDs and names)
            if ($request->has('categories')) {
                $categoryIds = [];

                foreach ($request->categories as $item) {
                    try {
                        if (is_numeric($item)) {
                            // It's an ID
                            $categoryIds[] = (int)$item;
                        } else {
                            // It's a name, find or create
                            $category = \App\Models\Category::firstOrCreate(
                                ['name' => trim($item)]
                            );
                            $categoryIds[] = $category->id;
                        }
                    } catch (\Exception $e) {
                        \Log::error('Error processing category', [
                            'item' => $item,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                if (!empty($categoryIds)) {
                    $contribute->categories()->sync($categoryIds);
                }
            }

            // Sync tags (handle both IDs and names)
            if ($request->has('tags')) {
                $tagIds = [];

                foreach ($request->tags as $item) {
                    try {
                        if (is_numeric($item)) {
                            // It's an ID
                            $tagIds[] = (int)$item;
                        } else {
                            // It's a name, find or create
                            $tag = \App\Models\Tag::firstOrCreate(
                                ['name' => trim($item)]
                            );
                            $tagIds[] = $tag->id;
                        }
                    } catch (\Exception $e) {
                        \Log::error('Error processing tag', [
                            'item' => $item,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                if (!empty($tagIds)) {
                    $contribute->tags()->sync($tagIds);
                }
            }

            $contribute->load('user', 'categories', 'tags');

            return response()->json([
                'message' => 'Contribution updated successfully',
                'contribute' => $contribute
            ]);
        } catch (\Exception $e) {
            \Log::error('Error updating contribution', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error updating contribution: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

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
        $result = $leaders->map(function ($item) {
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

    public function approved()
    {
        $contributes = Contribute::with('user', 'categories', 'tags')
            ->where('status', 'approved')
            ->latest()
            ->paginate(15);

        return response()->json($contributes);
    }

    /**
     * Get single approved contribution (public)
     * Returns a specific approved contribution for public viewing
     */
    public function showApproved($id)
    {
        $contribute = Contribute::with('user', 'categories', 'tags')
            ->where('id', $id)
            ->where('status', 'approved')
            ->firstOrFail();

        return response()->json($contribute);
    }

    public function myContributions()
    {
        $contributes = auth('api')->user()
            ->contributions()
            ->with('categories', 'tags')
            ->latest()
            ->get();

        return response()->json($contributes);
    }

    public function getFileData($id)
    {
        try {
            $contribute = Contribute::with(['user', 'categories', 'tags'])
                ->where('status', 'approved')
                ->findOrFail($id);

            if (!$contribute->file_path) {
                return response()->json([
                    'error' => 'No file associated with this contribution'
                ], 404);
            }

            // Get the full file path
            $filePath = storage_path('app/public/' . $contribute->file_path);

            if (!file_exists($filePath)) {
                return response()->json([
                    'error' => 'File not found'
                ], 404);
            }

            // Detect file type and parse accordingly
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            
            if ($extension === 'csv') {
                $data = $this->parseCsv($filePath);
            } elseif (in_array($extension, ['xlsx', 'xls'])) {
                $data = $this->parseExcel($filePath);
            } else {
                return response()->json([
                    'error' => 'Unsupported file format. Only CSV, XLS, and XLSX are supported.'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'file_type' => $extension,
                'headers' => $data['headers'] ?? [],
                'rows' => $data['rows'] ?? []
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to parse file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Parse CSV file
     */
    private function parseCsv($filePath)
    {
        $rows = [];
        $headers = [];

        if (($handle = fopen($filePath, 'r')) !== false) {
            // Get headers from first row
            $headers = fgetcsv($handle);
            
            // Clean headers (trim whitespace)
            $headers = array_map('trim', $headers);

            // Get data rows (limit to 1000 rows for performance)
            $rowCount = 0;
            while (($row = fgetcsv($handle)) !== false && $rowCount < 1000) {
                $rowData = [];
                $hasData = false; // Track if row has any non-empty values
                
                foreach ($headers as $index => $header) {
                    $value = isset($row[$index]) ? trim($row[$index]) : null;
                    
                    // Check if this cell has data
                    if ($value !== null && $value !== '') {
                        $hasData = true;
                    }
                    
                    // Try to convert to number if possible
                    if (is_numeric($value)) {
                        $value = $value + 0; // Convert to int or float
                    }
                    $rowData[$header] = $value;
                }
                
                // Only add row if it has at least one non-empty value
                if ($hasData) {
                    $rows[] = $rowData;
                    $rowCount++;
                }
            }
            fclose($handle);
        }

        return [
            'headers' => $headers,
            'rows' => $rows
        ];
    }

    /**
     * Parse Excel file (XLSX/XLS)
     */
    private function parseExcel($filePath)
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = min($worksheet->getHighestRow(), 1001); // Limit to 1000 rows + header
        $highestColumn = $worksheet->getHighestColumn();
        
        $rows = [];
        $headers = [];

        // Get headers from first row
        $headerRow = $worksheet->rangeToArray('A1:' . $highestColumn . '1', null, true, false)[0];
        $headers = array_map('trim', $headerRow);

        // Get data rows
        for ($row = 2; $row <= $highestRow; $row++) {
            $rowData = [];
            $hasData = false; // Track if row has any non-empty values
            $cells = $worksheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, null, true, false)[0];
            
            foreach ($headers as $index => $header) {
                $value = isset($cells[$index]) ? $cells[$index] : null;
                
                // Trim if string
                if (is_string($value)) {
                    $value = trim($value);
                }
                
                // Check if this cell has data
                if ($value !== null && $value !== '') {
                    $hasData = true;
                }
                
                // Convert numeric strings to numbers
                if (is_numeric($value)) {
                    $value = $value + 0;
                }
                $rowData[$header] = $value;
            }
            
            // Only add row if it has at least one non-empty value
            if ($hasData) {
                $rows[] = $rowData;
            }
        }

        return [
            'headers' => $headers,
            'rows' => $rows
        ];
    }
}