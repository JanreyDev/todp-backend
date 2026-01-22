<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contribute extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
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
        return $this->belongsToMany(Category::class, 'category_contribute');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'contribute_tag');
    }

    /**
     * Get all files associated with this contribution
     */
    public function files()
    {
        return $this->hasMany(ContributeFile::class);
    }
}