<?php

use App\Models\Tenant;
use App\Models\User;
use App\Models\Customer;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->user   = User::factory()->create(['tenant_id' => $this->tenant->id]);

    app()->instance('current.tenant', $this->tenant);
    $this->actingAs($this->user);
});

it('enforces tenant scope on customers', function () {
    $otherTenant = Tenant::factory()->create();

    Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Mine']);
    Customer::factory()->create(['tenant_id' => $otherTenant->id, 'name' => 'NotMine']);

    $customers = Customer::all();

    expect($customers)->toHaveCount(1)
        ->and($customers->first()->name)->toBe('Mine');
});

it('cannot access customers of another tenant', function () {
    $otherTenant   = Tenant::factory()->create();
    $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);

    $found = Customer::find($otherCustomer->id);

    expect($found)->toBeNull();
});
