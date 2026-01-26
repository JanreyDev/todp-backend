<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Contribute;
use App\Models\ContributeFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ContributeController extends Controller
{
    /**
     * Store a new contribution with multiple files (authenticated users only)
     * Status is automatically set to 'pending'
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'organization' => 'required|string|max:255',
            'request_type' => 'required|string|max:255',
            'message' => 'required|string',
            'files' => 'nullable|array|max:5', // Max 5 files
            'files.*' => 'file|mimes:xlsx,xls,csv|max:10240', // Each file max 10MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $data = $request->only(['title', 'organization', 'request_type', 'message']);
            $data['status'] = 'pending';

            // Create contribution
            $contribute = auth('api')->user()->contributions()->create($data);

            // Handle multiple file uploads
            if ($request->hasFile('files')) {
                $uploadedFiles = [];

                foreach ($request->file('files') as $file) {
                    $fileName = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                    $filePath = $file->storeAs('uploads', $fileName, 'public');

                    // Create file record
                    $contributeFile = ContributeFile::create([
                        'contribute_id' => $contribute->id,
                        'file_path' => $filePath,
                        'original_name' => $file->getClientOriginalName(),
                        'file_type' => $file->getClientOriginalExtension(),
                        'file_size' => $file->getSize(),
                    ]);

                    $uploadedFiles[] = $contributeFile;
                }

                // For backward compatibility, store first file path in main table
                if (count($uploadedFiles) > 0) {
                    $contribute->file_path = $uploadedFiles[0]->file_path;
                    $contribute->save();
                }
            }

            DB::commit();

            // Load relationships for response
            $contribute->load(['user', 'files']);

            return response()->json([
                'message' => 'Contribution submitted successfully and is pending review',
                'contribute' => $contribute,
                'files_count' => $contribute->files->count()
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to submit contribution',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index()
    {
        $contributes = Contribute::with(['user', 'categories', 'tags', 'files'])
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
                    'files' => $contribute->files->map(function ($file) {
                        return [
                            'id' => $file->id,
                            'original_name' => $file->original_name,
                            'file_type' => $file->file_type,
                            'file_size' => $file->file_size,
                            'formatted_size' => $file->formatted_size,
                            'file_path' => $file->file_path,
                        ];
                    }),
                ];
            });

        return response()->json($contributes);
    }

    public function show($id)
    {
        $contribute = Contribute::with(['user', 'categories', 'tags', 'files'])->findOrFail($id);
        return response()->json($contribute);
    }

    public function update(Request $request, $id)
    {
        try {
            $contribute = Contribute::findOrFail($id);

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

            if ($request->has('status')) {
                $contribute->status = $request->status;
                $contribute->save();
            }

            if ($request->has('categories')) {
                $categoryIds = [];
                foreach ($request->categories as $item) {
                    try {
                        if (is_numeric($item)) {
                            $categoryIds[] = (int)$item;
                        } else {
                            $category = \App\Models\Category::firstOrCreate(['name' => trim($item)]);
                            $categoryIds[] = $category->id;
                        }
                    } catch (\Exception $e) {
                        \Log::error('Error processing category', ['item' => $item, 'error' => $e->getMessage()]);
                    }
                }
                if (!empty($categoryIds)) {
                    $contribute->categories()->sync($categoryIds);
                }
            }

            if ($request->has('tags')) {
                $tagIds = [];
                foreach ($request->tags as $item) {
                    try {
                        if (is_numeric($item)) {
                            $tagIds[] = (int)$item;
                        } else {
                            $tag = \App\Models\Tag::firstOrCreate(['name' => trim($item)]);
                            $tagIds[] = $tag->id;
                        }
                    } catch (\Exception $e) {
                        \Log::error('Error processing tag', ['item' => $item, 'error' => $e->getMessage()]);
                    }
                }
                if (!empty($tagIds)) {
                    $contribute->tags()->sync($tagIds);
                }
            }

            $contribute->load(['user', 'categories', 'tags', 'files']);

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
        $leaders = Contribute::select('user_id')
            ->selectRaw('COUNT(*) as total_contributions')
            ->selectRaw('MAX(created_at) as last_contribution_date')
            ->where('status', 'approved')
            ->groupBy('user_id')
            ->orderByDesc('total_contributions')
            ->limit(10)
            ->get();

        $result = $leaders->map(function ($item) {
            $user = \App\Models\User::find($item->user_id);
            $lastContrib = Contribute::where('user_id', $item->user_id)
                ->where('status', 'approved')
                ->latest()
                ->first();

            // Get breakdown of request types for this user
            $requestTypes = Contribute::where('user_id', $item->user_id)
                ->where('status', 'approved')
                ->select('request_type', DB::raw('count(*) as count'))
                ->groupBy('request_type')
                ->pluck('count', 'request_type');

            return [
                'user' => [
                    'name' => $user->name ?? 'Unknown',
                    'email' => $user->email ?? '',
                ],
                'organization' => $lastContrib->organization ?? 'Independent',
                'total' => (int) $item->total_contributions,
                'request_types' => $requestTypes,
                'last_title' => $lastContrib->title ?? null,
                'last_date' => $lastContrib->created_at ?? null,
            ];
        });

        return response()->json($result);
    }

    public function approved()
    {
        $contributes = Contribute::with(['user', 'categories', 'tags', 'files'])
            ->where('status', 'approved')
            ->latest()
            ->paginate(15);

        return response()->json($contributes);
    }

    public function showApproved($id)
    {
        $contribute = Contribute::with(['user', 'categories', 'tags', 'files'])
            ->where('id', $id)
            ->where('status', 'approved')
            ->firstOrFail();

        return response()->json($contribute);
    }

    public function myContributions()
    {
        $contributes = auth('api')->user()
            ->contributions()
            ->with(['categories', 'tags', 'files'])
            ->latest()
            ->get();

        return response()->json($contributes);
    }

    /**
     * Get file data for a specific file in a contribution
     */
    public function getFileData($id, $fileId = null)
    {
        try {
            $contribute = Contribute::with(['user', 'categories', 'tags', 'files'])
                ->where('status', 'approved')
                ->findOrFail($id);

            // If fileId is provided, get specific file; otherwise get first file
            if ($fileId) {
                $file = ContributeFile::where('contribute_id', $id)
                    ->where('id', $fileId)
                    ->firstOrFail();
            } else {
                $file = $contribute->files()->first();

                if (!$file) {
                    // Fallback to old file_path if no files exist
                    if (!$contribute->file_path) {
                        return response()->json(['error' => 'No file associated with this contribution'], 404);
                    }
                    return $this->parseFileFromPath($contribute->file_path);
                }
            }

            $filePath = storage_path('app/public/' . $file->file_path);

            if (!file_exists($filePath)) {
                return response()->json(['error' => 'File not found'], 404);
            }

            $data = $this->parseFile($filePath, $file->file_type);

            return response()->json([
                'success' => true,
                'data' => $data,
                'file_info' => [
                    'id' => $file->id,
                    'name' => $file->original_name,
                    'type' => $file->file_type,
                    'size' => $file->formatted_size,
                ],
                'file_type' => $file->file_type,
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
     * Parse file based on extension
     */
    private function parseFile($filePath, $extension)
    {
        if ($extension === 'csv') {
            return $this->parseCsv($filePath);
        } elseif (in_array($extension, ['xlsx', 'xls'])) {
            return $this->parseExcel($filePath);
        }

        throw new \Exception('Unsupported file format');
    }

    private function parseCsv($filePath)
    {
        $rows = [];
        $headers = [];

        if (($handle = fopen($filePath, 'r')) !== false) {
            $headers = fgetcsv($handle);
            $headers = array_map('trim', $headers);

            $rowCount = 0;
            while (($row = fgetcsv($handle)) !== false && $rowCount < 1000) {
                $rowData = [];
                $hasData = false;

                foreach ($headers as $index => $header) {
                    $value = isset($row[$index]) ? trim($row[$index]) : null;

                    if ($value !== null && $value !== '') {
                        $hasData = true;
                    }

                    if (is_numeric($value)) {
                        $value = $value + 0;
                    }
                    $rowData[$header] = $value;
                }

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

    private function parseExcel($filePath)
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = min($worksheet->getHighestRow(), 1001);
        $highestColumn = $worksheet->getHighestColumn();

        $rows = [];
        $headers = [];

        $headerRow = $worksheet->rangeToArray('A1:' . $highestColumn . '1', null, true, false)[0];
        $headers = array_map('trim', $headerRow);

        for ($row = 2; $row <= $highestRow; $row++) {
            $rowData = [];
            $hasData = false;
            $cells = $worksheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, null, true, false)[0];

            foreach ($headers as $index => $header) {
                $value = isset($cells[$index]) ? $cells[$index] : null;

                if (is_string($value)) {
                    $value = trim($value);
                }

                if ($value !== null && $value !== '') {
                    $hasData = true;
                }

                if (is_numeric($value)) {
                    $value = $value + 0;
                }
                $rowData[$header] = $value;
            }

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
