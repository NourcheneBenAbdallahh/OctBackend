<?php

namespace App\Services;

use App\Models\Packaging;

class PackagingService
{
    public function create(array $data): Packaging
    {
        // Normalisation métier
        $data['type'] = strtoupper($data['type']);

        // Règle cohérence capacité
        if (!empty($data['capacity_value']) && empty($data['capacity_unit'])) {
            throw new \InvalidArgumentException("capacity_unit is required when capacity_value is provided.");
        }

        return Packaging::create($data);
    }

    public function update(Packaging $packaging, array $data): Packaging
    {
        if (isset($data['type'])) $data['type'] = strtoupper($data['type']);

        $capacityValue = array_key_exists('capacity_value', $data) ? $data['capacity_value'] : $packaging->capacity_value;
        $capacityUnit  = array_key_exists('capacity_unit', $data) ? $data['capacity_unit'] : $packaging->capacity_unit;

        if (!empty($capacityValue) && empty($capacityUnit)) {
            throw new \InvalidArgumentException("capacity_unit is required when capacity_value is provided.");
        }

        $packaging->update($data);
        return $packaging->refresh();
    }

    public function softDelete(Packaging $packaging): Packaging
    {
        $packaging->delete();
        return $packaging;
    }

    public function restore(int $id): Packaging
    {
        $packaging = Packaging::withTrashed()->findOrFail($id);
        $packaging->restore();
        return $packaging;
    }

    public function forceDelete(int $id): bool
    {
        $packaging = Packaging::withTrashed()->findOrFail($id);
        return (bool) $packaging->forceDelete();
    }
}