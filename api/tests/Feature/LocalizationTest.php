<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class LocalizationTest extends TestCase
{
    /** Spanish is the default locale, with English as the fallback. */
    public function test_default_locale_is_spanish(): void
    {
        $this->assertSame('es', app()->getLocale());
        $this->assertSame('en', app()->getFallbackLocale());
    }

    /** Validation messages come from lang/es. */
    public function test_validation_messages_are_in_spanish(): void
    {
        $validator = Validator::make(['precio' => null], ['precio' => 'required']);

        $this->assertSame('El campo precio es obligatorio.', $validator->errors()->first('precio'));
    }
}
