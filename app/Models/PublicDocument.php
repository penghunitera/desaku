<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PublicDocument extends Model { protected $guarded = []; protected function casts(): array { return ['published'=>'boolean','issued_at'=>'date']; } }
