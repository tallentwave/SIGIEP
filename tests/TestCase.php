<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Testes de regra de negócio não passam por 2FA; a suíte de 2FA religa por perfil.
        config(['sigiep.two_factor_required_roles' => []]);
    }
}
