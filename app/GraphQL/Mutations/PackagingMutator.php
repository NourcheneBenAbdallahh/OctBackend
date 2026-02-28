<?php

namespace App\GraphQL\Mutations;

use App\Models\Packaging;
use App\Services\PackagingService;

class PackagingMutator
{
    public function __construct(private PackagingService $service) {}

    public function create($_, array $args): Packaging
    {
        return $this->service->create($args['input']);
    }

    public function update($_, array $args): Packaging
    {
        $packaging = Packaging::findOrFail($args['id']);
        return $this->service->update($packaging, $args['input']);
    }

    public function delete($_, array $args): Packaging
    {
        $packaging = Packaging::findOrFail($args['id']);
        return $this->service->softDelete($packaging);
    }

    public function restore($_, array $args): Packaging
    {
        return $this->service->restore((int) $args['id']);
    }

    public function forceDelete($_, array $args): bool
    {
        return $this->service->forceDelete((int) $args['id']);
    }
}