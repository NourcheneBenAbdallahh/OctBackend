<?php

namespace App\GraphQL\Mutations;

use App\Services\LotService;

class LotMutation
{
    public function __construct(private LotService $lotService) {}

    public function create($_, array $args)
    {
        // args['input'] contient le payload
        return $this->lotService->createLotAndApply($args['input']);
    }
}