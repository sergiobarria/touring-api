<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Spatie\Health\Models\HealthCheckResultHistoryItem as BaseHealthCheckResultHistoryItem;

class HealthCheckResultHistoryItem extends BaseHealthCheckResultHistoryItem
{
    use HasUlids;
}
