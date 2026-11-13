<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('repara enlaces históricos sin cambiar autor lectura ni links externos y admite simulación', function () {
    config(['app.url' => 'https://taller.example.test']);
    $user = User::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);
    $id = (string) Str::uuid();
    $data = ['title' => 'Para hoy', 'actions' => [
        ['url' => 'http://localhost:8080/panel/para-hoy-page?pestana=turnos#lista'],
        ['url' => 'https://externo.example.test/documento'],
    ]];
    DB::table('notifications')->insert([
        'id' => $id, 'type' => 'test', 'notifiable_type' => User::class, 'notifiable_id' => $user->id,
        'data' => json_encode($data), 'read_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->artisan('notifications:repair-links --dry-run')->assertSuccessful();
    expect(json_decode(DB::table('notifications')->where('id', $id)->value('data'), true))->toBe($data);
    $this->artisan('notifications:repair-links')->assertSuccessful();
    $row = DB::table('notifications')->where('id', $id)->first();
    $updated = json_decode($row->data, true);
    expect($updated['actions'][0]['url'])->toBe('https://taller.example.test/panel/para-hoy-page?pestana=turnos#lista')
        ->and($updated['actions'][1]['url'])->toBe($data['actions'][1]['url'])
        ->and($row->notifiable_id)->toBe($user->id)
        ->and($row->read_at)->not->toBeNull();
    $this->artisan('notifications:repair-links')->expectsOutput('Notificaciones corregidas: 0')->assertSuccessful();
});
