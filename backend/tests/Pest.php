<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
| Liga a classe base TestCase aos testes dos diretórios Feature e Unit.
*/

pest()->extend(Tests\TestCase::class)
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations / Helpers
|--------------------------------------------------------------------------
*/

// nada extra por enquanto