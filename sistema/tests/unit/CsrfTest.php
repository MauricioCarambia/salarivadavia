<?php

use PHPUnit\Framework\TestCase;

class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_POST = [];
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
    }

    public function testCsrfTokenGeneraYReutilizaToken(): void
    {
        $token = csrf_token();

        $this->assertNotEmpty($token);
        $this->assertSame($token, csrf_token());
        $this->assertSame($token, $_SESSION['csrf_token']);
    }

    public function testCsrfFieldIncluyeElToken(): void
    {
        $token = csrf_token();

        $this->assertStringContainsString($token, csrf_field());
    }

    public function testCsrfValidateFallaSinToken(): void
    {
        csrf_token();

        $this->assertFalse(csrf_validate());
    }

    public function testCsrfValidateFallaConTokenIncorrecto(): void
    {
        csrf_token();
        $_POST['csrf_token'] = 'token_invalido';

        $this->assertFalse(csrf_validate());
    }

    public function testCsrfValidatePasaConTokenCorrectoEnPost(): void
    {
        $token = csrf_token();
        $_POST['csrf_token'] = $token;

        $this->assertTrue(csrf_validate());
    }

    public function testCsrfValidatePasaConTokenCorrectoEnHeader(): void
    {
        $token = csrf_token();
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;

        $this->assertTrue(csrf_validate());
    }

    public function testRequerirCsrfNoCortaRequestsGet(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        requerirCsrf();

        $this->assertTrue(true);
    }
}
