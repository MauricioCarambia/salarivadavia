<?php

use PHPUnit\Framework\TestCase;

class ConfigEnvTest extends TestCase
{
    public function testEnvDevuelveValorPorDefectoSiNoExiste(): void
    {
        $this->assertSame('default', env('CLAVE_QUE_NO_EXISTE_XYZ', 'default'));
    }

    public function testEnvDevuelveNullPorDefectoSiNoSeIndicaDefault(): void
    {
        $this->assertNull(env('OTRA_CLAVE_QUE_NO_EXISTE_XYZ'));
    }

    public function testEnvLeeValoresDefinidosEnEntorno(): void
    {
        putenv('TEST_ENV_VAR=hola');

        $this->assertSame('hola', env('TEST_ENV_VAR'));

        putenv('TEST_ENV_VAR');
    }
}
