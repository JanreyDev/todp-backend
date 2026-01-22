<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContributeFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'contribute_id',
        'file_path',
        'original_name',
        'file_type',
        'file_size',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    /**
     * Get the contribution that owns the file
     */
    public function contribute()
    {
        return $this->belongsTo(Contribute::class);
    }

    /**
     * Get formatted file size
     */
    public function getFormattedSizeAttribute()
    {
        $bytes = $this->file_size;
        if ($bytes === 0) return '0 Bytes';
        
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB'];
        $i = floor(log($bytes) / log($k));
        
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
}