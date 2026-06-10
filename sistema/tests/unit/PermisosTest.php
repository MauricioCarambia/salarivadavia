<?php

use PHPUnit\Framework\TestCase;

class PermisosTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testAdminTieneAccesoATodo(): void
    {
        $_SESSION['es_admin'] = true;

        $this->assertTrue(tieneAcceso('turnos'));
        $this->assertTrue(tieneAcceso('gestionar_roles'));
        $this->assertTrue(tieneAcceso('cualquier_cosa'));
    }

    public function testWildcardEnAccesosDaAccesoATodo(): void
    {
        $_SESSION['accesos'] = ['*'];

        $this->assertTrue(tieneAcceso('turnos'));
        $this->assertTrue(tieneAcceso('gestionar_roles'));
    }

    public function testRecepcionistaSoloTieneSusAccesos(): void
    {
        $_SESSION['accesos'] = ['inicio', 'turnos', 'pacientes'];

        $this->assertTrue(tieneAcceso('turnos'));
        $this->assertFalse(tieneAcceso('arrastre'));
        $this->assertFalse(tieneAcceso('gestionar_roles'));
    }

    public function testSinSesionNoTieneAcceso(): void
    {
        $this->assertFalse(tieneAcceso('turnos'));
    }
}
