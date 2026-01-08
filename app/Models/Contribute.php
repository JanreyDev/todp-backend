<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contribute extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', // Add this
        'title',
        'organization',
        'request_type',
        'message',
        'file_path',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function categories()
    {
        // Explicitly specify table name if needed
        return $this->belongsToMany(Category::class, 'category_contribute');
    }

    public function tags()
    {
        // Explicitly specify table name if needed
        return $this->belongsToMany(Tag::class, 'contribute_tag');
        // OR if your table is named 'tag_contribute':
        // return $this->belongsToMany(Tag::class, 'tag_contribute');
    }
}