<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'icon',
        'description',
    ];

    /**
     * Get the contributes (datasets) for this category
     */
    public function contributes()
    {
        return $this->belongsToMany(Contribute::class, 'category_contribute');
    }

    /**
     * Get only approved contributes
     */
    public function approvedContributes()
    {
        return $this->contributes()->where('status', 'approved');
    }
}