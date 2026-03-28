<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientFrequence extends Model
{
    protected $table = 'client_frequences';

    protected $fillable = [
        'designation',
        'slug',
        'commandes_minimum',
        'commandes_maximum',
        'intervalle_max_jours',
        'priorite',
    ];

    /**
     * @param  ?float  $moyenneJoursEntreCommandes  null si moins de 2 commandes datées
     */
    public function correspondAuxStats(int $nbCommandes, ?float $moyenneJoursEntreCommandes): bool
    {
        if ($nbCommandes < $this->commandes_minimum) {
            return false;
        }
        if ($this->commandes_maximum !== null && $nbCommandes > $this->commandes_maximum) {
            return false;
        }
        if ($this->intervalle_max_jours !== null) {
            if ($moyenneJoursEntreCommandes === null) {
                return false;
            }
            if ($moyenneJoursEntreCommandes > $this->intervalle_max_jours) {
                return false;
            }
        }

        return true;
    }
}
