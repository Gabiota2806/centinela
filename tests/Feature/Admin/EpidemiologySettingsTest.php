<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Livewire\Admin\EpidemiologySettings;
use App\Models\EpidemiologySetting;
use App\Models\User;
use App\Models\WeatherRecord;
use App\Services\Weather\OpenMeteoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pruebas del panel de configuración epidemiológica y control RBAC access-epidemiology (RF-GEO-03).
 */
class EpidemiologySettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget(OpenMeteoService::CONTINGENCY_CACHE_KEY);
    }

    /**
     * @return array<string, array{UserRole, int}>
     */
    public static function roleAccessProvider(): array
    {
        return [
            'director allowed (200)' => [UserRole::DIRECTOR, 200],
            'admission forbidden (403)' => [UserRole::ADMISSION, 403],
            'triage_nurse forbidden (403)' => [UserRole::TRIAGE_NURSE, 403],
            'doctor forbidden (403)' => [UserRole::DOCTOR, 403],
        ];
    }

    #[DataProvider('roleAccessProvider')]
    public function test_route_enforces_access_epidemiology_gate_by_role(UserRole $role, int $expectedStatus): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)
            ->get(route('epidemiology.settings'))
            ->assertStatus($expectedStatus);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('epidemiology.settings'))
            ->assertRedirect('/login');
    }

    public function test_sidebar_shows_epidemiology_link_only_to_director(): void
    {
        $director = User::factory()->create(['role' => UserRole::DIRECTOR]);
        $doctor = User::factory()->create(['role' => UserRole::DOCTOR]);

        $this->actingAs($director)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee(route('epidemiology.settings'), false);

        $this->actingAs($doctor)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee(route('epidemiology.settings'), false);
    }

    public function test_loads_contract_defaults_when_settings_table_is_empty(): void
    {
        $director = User::factory()->create(['role' => UserRole::DIRECTOR]);

        $this->actingAs($director);

        Livewire::test(EpidemiologySettings::class)
            ->assertSet('rain_threshold_mm', EpidemiologySetting::DEFAULT_RAIN_THRESHOLD_MM)
            ->assertSet('time_lag_days', EpidemiologySetting::DEFAULT_TIME_LAG_DAYS)
            ->assertSet('febrile_alert_threshold', EpidemiologySetting::DEFAULT_FEBRILE_ALERT_THRESHOLD)
            ->assertSet('high_risk_probability', EpidemiologySetting::DEFAULT_HIGH_RISK_PROBABILITY);

        $this->assertDatabaseCount('epidemiology_settings', 1);
    }

    public function test_director_can_update_and_persist_epidemiology_parameters(): void
    {
        $director = User::factory()->create([
            'role' => UserRole::DIRECTOR,
            'name' => 'Dra. Laura Romero',
        ]);

        $this->actingAs($director);

        Livewire::test(EpidemiologySettings::class)
            ->set('rain_threshold_mm', 55.5)
            ->set('time_lag_days', 12)
            ->set('febrile_alert_threshold', 8)
            ->set('high_risk_probability', 0.78)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Parámetros epidemiológicos actualizados correctamente.')
            ->assertSee('Dra. Laura Romero');

        $setting = EpidemiologySetting::query()->firstOrFail();

        $this->assertSame(55.5, $setting->rain_threshold_mm);
        $this->assertSame(12, $setting->time_lag_days);
        $this->assertSame(8, $setting->febrile_alert_threshold);
        $this->assertSame(0.78, $setting->high_risk_probability);
        $this->assertSame($director->id, $setting->updated_by);
    }

    public function test_validates_parameter_boundaries_including_time_lag_7_to_14_days(): void
    {
        $director = User::factory()->create(['role' => UserRole::DIRECTOR]);

        $this->actingAs($director);

        Livewire::test(EpidemiologySettings::class)
            ->set('rain_threshold_mm', 0)
            ->set('time_lag_days', 6)
            ->set('febrile_alert_threshold', 0)
            ->set('high_risk_probability', 1.2)
            ->call('save')
            ->assertHasErrors([
                'rain_threshold_mm',
                'time_lag_days',
                'febrile_alert_threshold',
                'high_risk_probability',
            ]);

        Livewire::test(EpidemiologySettings::class)
            ->set('time_lag_days', 15)
            ->call('save')
            ->assertHasErrors(['time_lag_days']);
    }

    public function test_unauthorized_role_cannot_mount_or_save_livewire_component(): void
    {
        $doctor = User::factory()->create(['role' => UserRole::DOCTOR]);

        $this->actingAs($doctor);

        Livewire::test(EpidemiologySettings::class)
            ->assertForbidden();
    }

    public function test_restore_defaults_populates_recommended_values_in_form(): void
    {
        $director = User::factory()->create(['role' => UserRole::DIRECTOR]);
        EpidemiologySetting::factory()->create([
            'rain_threshold_mm' => 90.0,
            'time_lag_days' => 14,
            'febrile_alert_threshold' => 15,
            'high_risk_probability' => 0.85,
        ]);

        $this->actingAs($director);

        Livewire::test(EpidemiologySettings::class)
            ->call('restoreDefaults')
            ->assertSet('rain_threshold_mm', EpidemiologySetting::DEFAULT_RAIN_THRESHOLD_MM)
            ->assertSet('time_lag_days', EpidemiologySetting::DEFAULT_TIME_LAG_DAYS)
            ->assertSet('febrile_alert_threshold', EpidemiologySetting::DEFAULT_FEBRILE_ALERT_THRESHOLD)
            ->assertSet('high_risk_probability', EpidemiologySetting::DEFAULT_HIGH_RISK_PROBABILITY);
    }

    public function test_displays_seven_day_weather_contingency_and_handles_offline_manual_sync(): void
    {
        $director = User::factory()->create(['role' => UserRole::DIRECTOR]);

        WeatherRecord::factory()->create([
            'recorded_date' => '2026-11-25',
            'precipitation_sum' => 46.5,
            'temperature_max' => 33.0,
            'temperature_min' => 21.0,
            'relative_humidity_mean' => 84.0,
        ]);

        Http::fake(static function (): never {
            throw new ConnectionException('Offline');
        });

        Log::shouldReceive('warning')->once();

        $this->actingAs($director);

        Livewire::test(EpidemiologySettings::class)
            ->assertSee('46,5 mm')
            ->assertSee('Supera umbral')
            ->call('syncWeatherNow')
            ->assertSee('Open-Meteo no responde en este momento')
            ->assertSee('46,5 mm');
    }
}
