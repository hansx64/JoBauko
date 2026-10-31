<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /**
     * Record an action, e.g. "organization.verified" or "opportunity.published".
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function log(string $action, ?Model $auditable = null, array $old = [], array $new = [], ?int $organizationId = null): AuditLog
    {
        return AuditLog::create([
            'user_id' => Auth::id(),
            'organization_id' => $organizationId,
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255) ?: null,
        ]);
    }

    /**
     * Record the attributes that changed on a model being saved.
     */
    public function logChanges(string $action, Model $model, ?int $organizationId = null): ?AuditLog
    {
        $changes = collect($model->getChanges())->except(['updated_at']);

        if ($changes->isEmpty()) {
            return null;
        }

        $old = $changes->keys()->mapWithKeys(fn (string $key) => [$key => $model->getOriginal($key)])->all();

        return $this->log($action, $model, $this->serializable($old), $this->serializable($changes->all()), $organizationId);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function serializable(array $values): array
    {
        return array_map(fn ($value) => match (true) {
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            default => $value,
        }, $values);
    }
}
