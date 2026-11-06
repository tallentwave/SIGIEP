<?php

return [
    'required' => 'O campo :attribute é obrigatório.',
    'email' => 'O campo :attribute deve ser um e-mail válido.',
    'date' => 'O campo :attribute não é uma data válida.',
    'array' => 'O campo :attribute deve ser uma lista.',
    'boolean' => 'O campo :attribute é inválido.',
    'integer' => 'O campo :attribute deve ser um número inteiro.',
    'exists' => 'O valor selecionado em :attribute é inválido.',
    'enum' => 'O valor selecionado em :attribute é inválido.',
    'min' => ['array' => 'Selecione ao menos :min item em :attribute.', 'string' => 'O campo :attribute deve ter ao menos :min caracteres.', 'numeric' => 'O campo :attribute deve ser ao menos :min.'],
    'max' => ['array' => 'O campo :attribute aceita no máximo :max itens.', 'string' => 'O campo :attribute aceita no máximo :max caracteres.', 'numeric' => 'O campo :attribute deve ser no máximo :max.'],
    'between' => ['numeric' => 'O campo :attribute deve estar entre :min e :max.'],
    'after' => 'O campo :attribute deve ser uma data posterior a :date.',
    'after_or_equal' => 'O campo :attribute deve ser igual ou posterior a :date.',
    'before_or_equal' => 'O campo :attribute não pode ser posterior a :date.',
    'attributes' => [
        'email' => 'e-mail',
        'password' => 'senha',
        'scheduled_at' => 'data/hora',
        'professional_id' => 'profissional',
        'duration_minutes' => 'duração',
        'destination' => 'destino',
        'status' => 'situação',
        'due_at' => 'prazo de retorno',
        'sent_at' => 'data de envio',
    ],
];
