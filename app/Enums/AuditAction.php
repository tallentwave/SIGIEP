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
    case PasswordResetRequested = 'senha_redefinicao_solicitada';
    case PasswordReset = 'senha_redefinida';
    case TwoFactorEnabled = '2fa_ativado';
    case TwoFactorDisabled = '2fa_desativado';
    case TwoFactorFailed = '2fa_falhou';
    case TwoFactorRecoveryUsed = '2fa_codigo_recuperacao';
    case TwoFactorAdminReset = '2fa_redefinido_admin';
    case Anonymized = 'anonimizou';
    case LegalHoldSet = 'guarda_legal_aplicada';
    case LegalHoldLifted = 'guarda_legal_retirada';
    case AuditPruned = 'auditoria_expurgada';
    case IntegrationGenerated = 'integracao_gerada';
    case IntegrationConfirmed = 'integracao_confirmada';
    case IntegrationWaived = 'integracao_dispensada';
    case Assigned = 'atribuiu_caso';

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
            self::PasswordResetRequested => 'Pedido de redefinição de senha',
            self::PasswordReset => 'Senha redefinida por e-mail',
            self::TwoFactorEnabled => '2FA ativado',
            self::TwoFactorDisabled => '2FA desativado',
            self::TwoFactorFailed => 'Falha no 2FA',
            self::TwoFactorRecoveryUsed => 'Código de recuperação usado',
            self::TwoFactorAdminReset => '2FA redefinido pelo admin',
            self::Anonymized => 'Caso anonimizado',
            self::LegalHoldSet => 'Guarda legal aplicada',
            self::LegalHoldLifted => 'Guarda legal retirada',
            self::AuditPruned => 'Trilha de auditoria expurgada',
            self::IntegrationGenerated => 'Arquivo/documento de integração gerado',
            self::IntegrationConfirmed => 'Integração confirmada',
            self::IntegrationWaived => 'Integração dispensada',
            self::Assigned => 'Atribuiu caso',
        };
    }
}
