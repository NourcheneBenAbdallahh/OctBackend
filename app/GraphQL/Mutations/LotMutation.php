<?php

namespace App\GraphQL\Mutations;

use App\Services\LotService;

class LotMutation
{
    public function create($_, array $args)
    {
        return app(LotService::class)->createLotAndApply($args['input']);
    }
}