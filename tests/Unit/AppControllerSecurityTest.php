<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../src/controllers/AppController.php';

final class TestableAppController extends AppController
{
    public function token(): string
    {
        return $this->getCsrfToken();
    }

    public function valid(?string $token): bool
    {
        return $this->isValidCsrfToken($token);
    }
}

final class AppControllerSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testGeneratedCsrfTokenIsAccepted(): void
    {
        $controller = new TestableAppController();
        $token = $controller->token();

        $this->assertNotEmpty($token);
        $this->assertTrue($controller->valid($token));
    }

    public function testInvalidCsrfTokenIsRejected(): void
    {
        $controller = new TestableAppController();
        $controller->token();

        $this->assertFalse($controller->valid('invalid-token'));
    }
}
