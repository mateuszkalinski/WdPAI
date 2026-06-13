<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../src/services/AdminService.php';

final class AdminServiceTest extends TestCase
{
    public function testSlugifyTransliteratesPolishExerciseName(): void
    {
        $service = new AdminService();

        $this->assertSame('zolnierskie-wyciskanie', $service->slugify('Żołnierskie wyciskanie'));
    }
}
