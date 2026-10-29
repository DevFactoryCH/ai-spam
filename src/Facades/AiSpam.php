<?php

namespace DevFactory\AiSpam\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \DevFactory\AiSpam\PendingSubmission hold(string $form, array $payload)
 * @method static \DevFactory\AiSpam\Enums\SpamReason|null preFilter(\Illuminate\Support\Collection|array $fields)
 *
 * @see \DevFactory\AiSpam\AiSpam
 */
class AiSpam extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \DevFactory\AiSpam\AiSpam::class;
    }
}
