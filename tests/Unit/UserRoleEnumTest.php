<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\User;
use Tests\TestCase;

/**
 * CEN-16: Pruebas unitarias del Enum UserRole y métodos de rol en el modelo User.
 */
class UserRoleEnumTest extends TestCase
{
    /**
     * Verifica que el Enum contenga exactamente los 4 roles con sus valores string especificados.
     */
    public function test_user_role_enum_has_exact_four_cases_and_backing_values(): void
    {
        $cases = UserRole::cases();

        $this->assertCount(4, $cases);
        $this->assertSame('admission', UserRole::ADMISSION->value);
        $this->assertSame('triage_nurse', UserRole::TRIAGE_NURSE->value);
        $this->assertSame('doctor', UserRole::DOCTOR->value);
        $this->assertSame('director', UserRole::DIRECTOR->value);
    }

    /**
     * Verifica las etiquetas legibles en español para cada rol del CAPS.
     */
    public function test_user_role_labels_match_specification(): void
    {
        $this->assertSame('Admisión / Mesa de Entrada', UserRole::ADMISSION->label());
        $this->assertSame('Enfermero de Triage', UserRole::TRIAGE_NURSE->label());
        $this->assertSame('Médico de Guardia', UserRole::DOCTOR->label());
        $this->assertSame('Dirección de CAPS', UserRole::DIRECTOR->label());
    }

    /**
     * Verifica el nombre de ruta post-login asociada a cada rol.
     */
    public function test_user_role_home_routes_match_specification(): void
    {
        $this->assertSame('patients.index', UserRole::ADMISSION->homeRoute());
        $this->assertSame('triage.index', UserRole::TRIAGE_NURSE->homeRoute());
        $this->assertSame('consultations.index', UserRole::DOCTOR->homeRoute());
        $this->assertSame('dashboard', UserRole::DIRECTOR->homeRoute());
    }

    /**
     * Verifica los métodos de conveniencia booleana y homeRoute() en el modelo User.
     */
    public function test_user_model_role_helper_methods(): void
    {
        $admission = new User(['role' => UserRole::ADMISSION]);
        $this->assertTrue($admission->isAdmission());
        $this->assertFalse($admission->isTriageNurse());
        $this->assertFalse($admission->isDoctor());
        $this->assertFalse($admission->isDirector());
        $this->assertSame('patients.index', $admission->homeRoute());

        $nurse = new User(['role' => UserRole::TRIAGE_NURSE]);
        $this->assertFalse($nurse->isAdmission());
        $this->assertTrue($nurse->isTriageNurse());
        $this->assertFalse($nurse->isDoctor());
        $this->assertFalse($nurse->isDirector());
        $this->assertSame('triage.index', $nurse->homeRoute());

        $doctor = new User(['role' => UserRole::DOCTOR]);
        $this->assertFalse($doctor->isAdmission());
        $this->assertFalse($doctor->isTriageNurse());
        $this->assertTrue($doctor->isDoctor());
        $this->assertFalse($doctor->isDirector());
        $this->assertSame('consultations.index', $doctor->homeRoute());

        $director = new User(['role' => UserRole::DIRECTOR]);
        $this->assertFalse($director->isAdmission());
        $this->assertFalse($director->isTriageNurse());
        $this->assertFalse($director->isDoctor());
        $this->assertTrue($director->isDirector());
        $this->assertSame('dashboard', $director->homeRoute());
    }
}
