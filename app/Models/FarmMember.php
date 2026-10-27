<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Relations\Pivot;

class FarmMember extends Pivot
{
    use Auditable;

    protected $table = 'farm_user';

    protected array $auditOnly = ['user_id', 'role'];

    public function auditLabel(): string
    {
        $name = $this->getAttribute('user_id')
            ? User::query()->whereKey($this->getAttribute('user_id'))->value('name')
            : null;

        return 'সদস্য ' . ($name ?: '#' . $this->getAttribute('user_id'));
    }
}
