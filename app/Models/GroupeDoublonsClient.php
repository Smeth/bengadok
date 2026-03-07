<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GroupeDoublonsClient extends Model
{
    protected $table = 'groupe_doublons_clients';

    protected $fillable = [
        'statut',
        'principal_client_id',
        'criteres',
    ];

    protected $casts = [
        'criteres' => 'array',
    ];

    public function principalClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'principal_client_id');
    }

    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_groupe_doublons', 'groupe_doublons_id', 'client_id')
            ->withPivot('is_principal')
            ->withTimestamps();
    }
}
