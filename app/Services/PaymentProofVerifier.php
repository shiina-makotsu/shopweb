<?php

namespace App\Services;

use App\Models\Order;
use Symfony\Component\Process\Process;
use Throwable;

class PaymentProofVerifier
{
    public function __construct(private readonly PaymentProofStorage $storage) {}

    public function verify(Order $order, ?string $path, ?string $textProof = null): PaymentProofVerificationResult
    {
        $textProof = trim((string) $textProof);

        if ($textProof !== '') {
            $result = $this->matchText($order, $textProof, 'text');

            if ($result->exactMatch) {
                return $result;
            }
        }

        if (blank($path)) {
            return new PaymentProofVerificationResult(false);
        }

        $ocrText = $this->readImageText((string) $path);

        if ($ocrText === null || trim($ocrText) === '') {
            return new PaymentProofVerificationResult(false);
        }

        return $this->matchText($order, $ocrText, 'ocr');
    }

    private function matchText(Order $order, string $text, string $source): PaymentProofVerificationResult
    {
        $normalizedText = strtoupper((string) preg_replace('/[^A-Z0-9]+/i', '', $text));
        $normalizedOrderNumber = strtoupper((string) preg_replace('/[^A-Z0-9]+/i', '', (string) $order->order_number));
        $orderMatches = $normalizedOrderNumber !== '' && str_contains($normalizedText, $normalizedOrderNumber);
        $amountMatches = $this->containsAmount($text, (int) $order->total_cents);

        return new PaymentProofVerificationResult(
            $orderMatches && $amountMatches,
            $orderMatches ? (string) $order->order_number : null,
            $amountMatches ? (int) $order->total_cents : null,
            $source,
        );
    }

    private function containsAmount(string $text, int $amountCents): bool
    {
        if ($amountCents < 0) {
            return false;
        }

        $amount = number_format($amountCents / 100, 2, '.', '');
        $decimalPattern = preg_quote($amount, '/');

        if (preg_match('/(?<!\d)'.$decimalPattern.'(?!\d)/u', str_replace(',', '', $text)) === 1) {
            return true;
        }

        if ($amountCents % 100 !== 0) {
            return false;
        }

        $whole = (string) intdiv($amountCents, 100);
        $currencyOrLabel = '(?:[￥¥]\s*'.$whole.'(?:\.00)?|'.$whole.'(?:\.00)?\s*元|(?:实付|支付|付款|金额|合计|到账|amount|paid|total)\D{0,12}'.$whole.'(?:\.00)?)';

        return preg_match('/'.$currencyOrLabel.'/iu', str_replace(',', '', $text)) === 1;
    }

    private function readImageText(string $path): ?string
    {
        $binary = trim((string) config('services.payment_proof_ocr.binary', 'tesseract'));

        if ($binary === '') {
            return null;
        }

        try {
            $contents = $this->storage->contents($path);
        } catch (Throwable) {
            return null;
        }

        if ($contents === null || $contents === '') {
            return null;
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'shop-proof-');

        if (! is_string($temporaryPath) || file_put_contents($temporaryPath, $contents) === false) {
            return null;
        }

        try {
            $process = new Process([
                $binary,
                $temporaryPath,
                'stdout',
                '-l',
                (string) config('services.payment_proof_ocr.languages', 'chi_sim+eng'),
                '--psm',
                '6',
            ]);
            $process->setTimeout(max(1, (int) config('services.payment_proof_ocr.timeout', 10)));
            $process->run();

            return $process->isSuccessful() ? $process->getOutput() : null;
        } catch (Throwable) {
            return null;
        } finally {
            @unlink($temporaryPath);
        }
    }
}
