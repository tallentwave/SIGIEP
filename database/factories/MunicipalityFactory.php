<?php

namespace Database\Factories;

use App\Models\Municipality;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Municipality> */
class MunicipalityFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->city();

        return ['name' => $name, 'uf' => 'SP', 'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'active' => true, 'accepts_public_reports' => true];
    }

    /** Município padrão compartilhado pelos usuários de teste que não pedem outro. */
    public static function defaultId(): int
    {
        return Municipality::firstOrCreate(['slug' => 'padrao-sp'], ['name' => 'Padrão', 'uf' => 'SP'])->id;
    }
}
