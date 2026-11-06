<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactor
{
    public function __construct(private Google2FA $g2fa = new Google2FA) {}

    public function newSecret(): string
    {
        return $this->g2fa->generateSecretKey(32);
    }

    public function qrSvg(User $user, string $secret): string
    {
        $url = $this->g2fa->getQRCodeUrl(config('app.name', 'SIGIEP'), $user->email, $secret);
        $svg = (new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd)))->writeString($url);

        return preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg);
    }

    /**
     * Valida o código TOTP rejeitando reuso: devolve o "passo" aceito ou null.
     * O passo é gravado para que o mesmo código não valha duas vezes.
     */
    public function verify(string $secret, string $code, ?int $lastStep = null): ?int
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6) {
            return null;
        }
        $step = $lastStep !== null
            ? $this->g2fa->verifyKeyNewer($secret, $code, $lastStep, 1)
            : $this->g2fa->verifyKey($secret, $code, 1);

        if ($step === false || $step === null) {
            return null;
        }

        return is_int($step) ? $step : (int) floor(time() / 30);
    }

    /** @return array{0: list<string>, 1: list<string>} [códigos em texto, hashes para guardar] */
    public function newRecoveryCodes(int $n = 8): array
    {
        $plain = collect(range(1, $n))->map(fn () => strtolower(Str::random(5).'-'.Str::random(5)))->all();

        return [$plain, array_map([$this, 'hash'], $plain)];
    }

    public function hash(string $code): string
    {
        return hash('sha256', strtolower(trim($code)));
    }

    /** Consome um código de recuperação. Devolve true se válido (e o remove). */
    public function useRecoveryCode(User $user, string $code): bool
    {
        $hash = $this->hash($code);
        $codes = $user->two_factor_recovery_codes ?? [];
        foreach ($codes as $i => $stored) {
            if (hash_equals($stored, $hash)) {
                unset($codes[$i]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }
}
