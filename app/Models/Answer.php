<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Answer extends Model
{
    use HasFactory;

    protected $fillable = [
        'line_user_id',
        'step',
        'answer',
    ];

    /**
     * Database used by this model
     *
     * @var string
     */
    protected $connection = 'sqlite'; // お好みで

    /**
     * Tables associated with the model
     *
     * @var string
     */
    protected $table = 'answers';
}
