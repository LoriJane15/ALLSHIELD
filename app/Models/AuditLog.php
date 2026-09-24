<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id', 'action', 'entity_type', 'entity_id', 'previous_values',
        'new_values', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['previous_values' => 'array', 'new_values' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function eventReference(): string
    {
        return 'AUD-'.str_pad((string) $this->getKey(), 8, '0', STR_PAD_LEFT);
    }

    public function entityLabel(): string
    {
        return str(class_basename($this->entity_type))->headline()->toString();
    }

    public function moduleLabel(): string
    {
        $entity = class_basename($this->entity_type);

        return match (true) {
            $entity === 'User' => 'User Management',
            $entity === 'GovAgency' => 'Government Agencies',
            str_starts_with($entity, 'Rcsp') => 'RCSP',
            str_starts_with($entity, 'Implementation'), $entity === 'AgencyImplanResponse' => 'IMPLAN',
            str_starts_with($entity, 'FormerRebel'), str_starts_with($entity, 'Fr') => 'Former Rebel Registry',
            default => $this->entityLabel(),
        };
    }

    public function actorRoleLabel(): string
    {
        if (! $this->user) {
            return 'Actor unavailable';
        }

        return (string) config(
            "shield.roles.{$this->user->role}.label",
            str($this->user->role)->replace('_', ' ')->title()->toString()
        );
    }

    public function actorContext(): ?string
    {
        if (! $this->user) {
            return null;
        }

        if ($this->user->municipality) {
            return $this->user->municipality->name;
        }

        if ($this->user->govAgency) {
            return $this->user->govAgency->acronym ?: $this->user->govAgency->name;
        }

        return null;
    }
}
