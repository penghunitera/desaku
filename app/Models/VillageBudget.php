<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class VillageBudget extends Model { protected $guarded = []; protected function casts(): array { return ['published'=>'boolean','budget_amount'=>'decimal:2','realized_amount'=>'decimal:2']; } }
