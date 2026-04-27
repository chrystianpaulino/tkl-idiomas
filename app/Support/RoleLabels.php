<?php

namespace App\Support;

/**
 * Maps internal role identifiers to human-readable PT-BR labels.
 *
 * Centralised so emails, UI fallbacks, and any future export feature share
 * the same wording. Internal identifiers (`super_admin`, `school_admin`,
 * `professor`, `aluno`) MUST stay in English -- they are the source of truth
 * for authorization checks throughout the codebase.
 */
class RoleLabels
{
    /**
     * @var array<string, string>
     */
    private const LABELS = [
        'super_admin' => 'Super Administrador',
        'school_admin' => 'Administrador',
        'professor' => 'Professor',
        'aluno' => 'Aluno',
    ];

    /**
     * Returns the PT-BR label for a role, falling back to the raw role
     * identifier when an unknown value is passed in. Defensive default
     * so missing/typo'd roles render visibly instead of silently empty.
     */
    public static function for(string $role): string
    {
        return self::LABELS[$role] ?? $role;
    }
}
