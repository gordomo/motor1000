<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\Vehicle;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = app('current.tenant')->id;

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $vehicleData = $data['vehicle'] ?? null;
        unset($data['vehicle']);

        return DB::transaction(function () use ($data, $vehicleData): Customer {
            /** @var Customer $customer */
            $customer = static::getModel()::create($data);

            $hasVehicleInput = is_array($vehicleData) &&
                filled($vehicleData['license_plate'] ?? null) &&
                filled($vehicleData['brand'] ?? null) &&
                filled($vehicleData['model'] ?? null) &&
                filled($vehicleData['year'] ?? null);

            if ($hasVehicleInput) {
                Vehicle::create([
                    'tenant_id' => $customer->tenant_id,
                    'customer_id' => $customer->id,
                    'license_plate' => $vehicleData['license_plate'],
                    'brand' => $vehicleData['brand'],
                    'model' => $vehicleData['model'],
                    'year' => (int) $vehicleData['year'],
                    'vin' => $vehicleData['vin'] ?? null,
                    'mileage' => (int) ($vehicleData['mileage'] ?? 0),
                    'fuel_type' => $vehicleData['fuel_type'] ?? 'flex',
                    'transmission' => $vehicleData['transmission'] ?? 'manual',
                    'notes' => $vehicleData['notes'] ?? null,
                ]);
            }

            return $customer;
        });
    }
}
