<?php

namespace App\Enums;

enum AuditAction: string
{
    case Login = 'login';
    case LoginFailed = 'login_falhou';
    case Logout = 'logout';
    case Viewed = 'visualizou';
    case ViewedReport = 'leu_relato';
    case Created = 'criou';
    case Updated = 'alterou';
    case Deleted = 'excluiu';
    case Denied = 'acesso_negado';
    case Claimed = 'assumiu_caso';
    case Exported = 'exportou';
    case PublicReport = 'denuncia_publica';

    public function label(): string
    {
        return match ($this) {
            self::Login => 'Login',
            self::LoginFailed => 'Falha de login',
            self::Logout => 'Logout',
            self::Viewed => 'Visualizou',
            self::ViewedReport => 'Leu relato sigiloso',
            self::Created => 'Criou',
            self::Updated => 'Alterou',
            self::Deleted => 'Excluiu',
            self::Denied => 'Acesso negado',
            self::Claimed => 'Assumiu caso',
            self::Exported => 'Gerou relatório/PDF',
            self::PublicReport => 'Denúncia pública recebida',
        };
    }
}
